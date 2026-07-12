<?php

declare(strict_types=1);

namespace Laradocs\Extensions;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Laradocs\Contracts\HtmlExtension;
use Laradocs\Contracts\MarkdownExtension;
use Laradocs\Support\CodeAwareReplacer;
use Laradocs\Support\Html;

/**
 * Renders $…$ (inline) and $$…$$ (display) math with KaTeX, loaded lazily
 * per-page. Math delimiters are extracted from markdown before CommonMark
 * runs so the parser never sees or mangles the LaTeX source.
 *
 * When Node.js and the katex npm package are resolvable, expressions are
 * server-side rendered to KaTeX HTML (no FOUC). Otherwise the bootstrap
 * script handles client-side rendering using KaTeX's synchronous render()
 * API — the FOUC is minimal because KaTeX renders synchronously.
 *
 * Only pages that actually contain a math expression load the KaTeX assets.
 */
final class KatexExtension implements HtmlExtension, MarkdownExtension
{
    /**
     * @readonly
     * @var string
     */
    private $js;
    /**
     * @readonly
     * @var string
     */
    private $css;
    /**
     * @readonly
     * @var bool
     */
    private $ssr = false;
    /**
     * @readonly
     * @var string|null
     */
    private $nodeBin;
    public function __construct(string $js, string $css, bool $ssr = false, ?string $nodeBin = null)
    {
        $this->js = $js;
        $this->css = $css;
        $this->ssr = $ssr;
        $this->nodeBin = $nodeBin;
    }

    // ── MarkdownExtension ────────────────────────────────────────────────────

    public function processMarkdown(string $markdown): string
    {
        // Block math ($$…$$) is extracted first via a state machine that
        // honours fenced code blocks, so the single-$ pass below never sees
        // the double-dollar delimiters.
        $markdown = $this->extractBlockMath($markdown);

        return CodeAwareReplacer::apply($markdown, function (string $text): string {
            // Single-line display math: $$expr$$ on the same line.
            $text = (string) preg_replace_callback(
                '/\$\$(.+?)\$\$/s',
                function (array $m) {
                    return $this->spanPlaceholder('block', trim($m[1]));
                },
                $text,
            );

            // Inline math: $expr$ — single $ not adjacent to another $. The
            // body is any run of escaped sequences (so \$ stays inside the
            // expression) or non-delimiter characters.
            $text = (string) preg_replace_callback(
                '/(?<!\$)\$(?!\$)((?:\\\\.|[^$\n])+)\$(?!\$)/',
                function (array $m) {
                    return $this->spanPlaceholder('inline', trim($m[1]));
                },
                $text,
            );

            return $text;
        });
    }

    /**
     * State-machine extraction of multi-line $$\n…\n$$ blocks.
     * Fenced code blocks are tracked so math inside examples is left alone.
     */
    private function extractBlockMath(string $markdown): string
    {
        $lines = explode("\n", $markdown);
        $output = [];
        $fence = null;
        /** @var list<string>|null $math */
        $math = null;

        foreach ($lines as $line) {
            if ($this->processFenceLine($line, $fence)) {
                $output[] = $line;

                continue;
            }

            if ($math !== null) {
                if (trim($line) === '$$') {
                    $output[] = $this->divPlaceholder(implode("\n", $math));
                    $math = null;
                } else {
                    $math[] = $line;
                }

                continue;
            }

            if (trim($line) === '$$') {
                $math = [];

                continue;
            }

            $output[] = $line;
        }

        // Unclosed block: restore verbatim rather than silently drop content.
        if ($math !== null) {
            $output[] = '$$';
            array_push($output, ...$math);
        }

        return implode("\n", $output);
    }

    /**
     * Updates $fence state and returns true if $line belongs to a fenced block.
     */
    private function processFenceLine(string $line, ?string &$fence): bool
    {
        if ($fence === null) {
            if (preg_match('/^\s{0,3}(`{3,}|~{3,})/', $line, $m) !== 1) {
                return false;
            }

            $fence = $m[1];

            return true;
        }

        if (preg_match('/^\s{0,3}' . $fence[0] . '{' . strlen($fence) . ',}\s*$/', $line) === 1) {
            $fence = null;
        }

        return true;
    }

    // ── HtmlExtension ────────────────────────────────────────────────────────

    public function processHtml(string $html): string
    {
        return Html::mutate($html, function (DOMDocument $dom, DOMElement $body): void {
            $xpath = new DOMXPath($dom);
            $nodes = $xpath->query('//*[@data-laradocs-katex]', $body);

            if ($nodes === false || $nodes->length === 0) {
                return;
            }

            $elements = iterator_to_array($nodes);

            if ($this->ssr) {
                $this->applySsr($dom, $elements);
            }

            $body->appendChild($this->bootstrap($dom));
        });
    }

    /**
     * Attempt server-side rendering via Node.js + the katex npm package.
     * Keeps the wrapper element in the DOM (so the CSS is still loaded) but
     * replaces its children with pre-rendered KaTeX HTML and marks it with
     * data-katex-rendered so the client-side script skips it.
     *
     * @param  array<int, \DOMNode|\DOMNameSpaceNode>  $elements
     */
    private function applySsr(DOMDocument $dom, array $elements): void
    {
        // Only DOMElements carry the data-* attributes we render from. Filter
        // once so the expression list and the elements stay index-aligned.
        $targets = array_values(array_filter(
            $elements,
            static function ($el): bool {
                return $el instanceof DOMElement;
            },
        ));

        $exprs = array_map(static function (DOMElement $el): array {
            return [
                'expr' => $el->getAttribute('data-expr'),
                'display' => $el->getAttribute('data-laradocs-katex') === 'block',
            ];
        }, $targets);

        $rendered = $this->runKatexNode($exprs);

        foreach ($targets as $i => $el) {
            $html = $rendered[$i] ?? null;

            if ($html === null) {
                continue;
            }

            while ($el->firstChild) {
                $el->removeChild($el->firstChild);
            }

            foreach (Html::fragment($dom, $html) as $node) {
                $el->appendChild($node);
            }

            $el->setAttribute('data-katex-rendered', '1');
        }
    }

    /**
     * Call Node.js to render a batch of expressions via katex.renderToString().
     *
     * @param  list<array{expr: string, display: bool}>  $exprs
     * @return list<string|null> null for any expression that could not be rendered
     */
    private function runKatexNode(array $exprs): array
    {
        $empty = array_fill(0, count($exprs), null);

        // empty() covers the no-expressions guard; json_encode covers encode failure.
        $input = empty($exprs) ? null : json_encode($exprs);

        if (! is_string($input)) {
            return $empty; // @codeCoverageIgnore
        }

        $output = $this->spawnNode($input);

        if ($output === null) {
            return $empty;
        }

        $results = json_decode($output, true);
        $toNullable = static function ($r): ?string {
            return is_string($r) ? $r : null;
        };

        return is_array($results)
            ? array_map($toNullable, array_values($results))
            : $empty;
    }

    /**
     * Writes the KaTeX Node script to a temp file, spawns Node.js, and returns
     * stdout on success or null on any failure.
     */
    private function spawnNode(string $input): ?string
    {
        if (! function_exists('proc_open')) {
            return null; // @codeCoverageIgnore
        }

        // Inline Node script — written to a temp file so we can pass the
        // expression JSON as a command-line argument and avoid shell quoting.
        $script = <<<'NODE'
            const input = JSON.parse(process.argv[2]);
            try {
              const katex = require('katex');
              const out = input.map(function (e) {
                try {
                  return katex.renderToString(e.expr, { displayMode: e.display, throwOnError: false, output: 'html' });
                } catch (err) { return null; }
              });
              process.stdout.write(JSON.stringify(out));
            } catch (e) {
              process.stdout.write(JSON.stringify(input.map(function () { return null; })));
            }
            NODE;

        $tmpFile = sys_get_temp_dir() . '/laradocs-katex-' . substr(hash('sha256', $script), 0, 8) . '.cjs';

        if (! file_exists($tmpFile)) {
            file_put_contents($tmpFile, $script);
        }

        $node = $this->nodeBin ?? 'node';
        $cmd = escapeshellarg($node) . ' ' . escapeshellarg($tmpFile) . ' ' . escapeshellarg($input);
        $descriptors = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];
        $proc = proc_open($cmd, $descriptors, $pipes);

        if (! is_resource($proc)) {
            return null; // @codeCoverageIgnore
        }

        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        return ($code === 0 && is_string($output)) ? $output : null;
    }

    private function bootstrap(DOMDocument $dom): DOMElement
    {
        $js = json_encode($this->js, JSON_UNESCAPED_SLASHES);
        $css = json_encode($this->css, JSON_UNESCAPED_SLASHES);
        $js = is_string($js) ? $js : '""';
        $css = is_string($css) ? $css : '""';

        $script = $dom->createElement('script');
        $script->appendChild($dom->createTextNode(
            str_replace(['__JS__', '__CSS__'], [$js, $css], self::BOOTSTRAP),
        ));

        return $script;
    }

    // ── Placeholders ─────────────────────────────────────────────────────────

    /**
     * Block math on its own lines becomes a <div> so CommonMark treats it as
     * an HTML block (not wrapping it in a <p>).
     */
    private function divPlaceholder(string $expr): string
    {
        $enc = htmlspecialchars($expr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return "\n"
            . '<div class="laradocs-katex-block" data-laradocs-katex="block" data-expr="' . $enc . '">'
            . $enc
            . '</div>'
            . "\n";
    }

    /**
     * Inline or single-line display math becomes a <span> so it survives
     * inside an existing paragraph without breaking the block structure.
     */
    private function spanPlaceholder(string $mode, string $expr): string
    {
        $enc = htmlspecialchars($expr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $cls = $mode === 'block' ? 'laradocs-katex-block' : 'laradocs-katex-inline';

        return '<span class="' . $cls . '" data-laradocs-katex="' . $mode . '" data-expr="' . $enc . '">'
            . $enc
            . '</span>';
    }

    // ── Bootstrap script ─────────────────────────────────────────────────────

    /**
     * Self-contained, idempotent per-page loader.
     *
     * - Always injects the KaTeX stylesheet (needed whether SSR'd or not).
     * - Only loads katex.min.js when there are expressions without
     *   data-katex-rendered (i.e. not yet server-side rendered).
     * - KaTeX.render() is synchronous, so there is no observable layout shift
     *   once the script executes.
     */
    private const BOOTSTRAP = <<<'JS'
        (function () {
          if (window.__laradocsKatex) return;
          window.__laradocsKatex = true;
          var JS = __JS__;
          var CSS = __CSS__;

          function loadCSS() {
            if (document.querySelector('[data-laradocs-katex-css]')) return;
            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = CSS;
            link.setAttribute('data-laradocs-katex-css', '');
            document.head.appendChild(link);
          }

          function render() {
            document.querySelectorAll('[data-laradocs-katex]:not([data-katex-rendered])').forEach(function (el) {
              var expr = el.getAttribute('data-expr') || '';
              var displayMode = el.getAttribute('data-laradocs-katex') === 'block';
              try {
                window.katex.render(expr, el, { displayMode: displayMode, throwOnError: false });
                el.setAttribute('data-katex-rendered', '1');
              } catch (e) {}
            });
          }

          function start() {
            var all = document.querySelectorAll('[data-laradocs-katex]');
            if (!all.length) return;

            loadCSS();

            var unrendered = document.querySelectorAll('[data-laradocs-katex]:not([data-katex-rendered])');
            if (!unrendered.length) return; // all SSR'd; CSS is sufficient

            var s = document.createElement('script');
            s.src = JS;
            s.onload = render;
            document.head.appendChild(s);
          }

          if (document.readyState !== 'loading') start();
          else document.addEventListener('DOMContentLoaded', start);
        })();
        JS;
}

<?php

declare(strict_types=1);

namespace Laradocs\Extensions;

use DOMDocument;
use DOMElement;
use Laradocs\Contracts\HtmlExtension;
use Laradocs\Support\Html;

/**
 * Renders ```mermaid fenced code blocks as SVG diagrams.
 *
 * The graph source is left in the document as a styled <pre> so it degrades to
 * a readable code block when JavaScript is disabled. On pages that actually use
 * a diagram — and only those — a small bootstrap script is appended that lazily
 * imports mermaid.js from a CDN, maps the active dark-mode tokens onto mermaid's
 * theme variables, and re-renders whenever the colour scheme changes.
 */
final class MermaidExtension implements HtmlExtension
{
    /**
     * @readonly
     * @var string
     */
    private $src;
    public function __construct(string $src)
    {
        $this->src = $src;
    }

    public function processHtml(string $html): string
    {
        return Html::mutate($html, function (DOMDocument $dom, DOMElement $body): void {
            $found = false;

            /** @var DOMElement $pre */
            foreach (iterator_to_array($body->getElementsByTagName('pre')) as $pre) {
                $code = $pre->getElementsByTagName('code')->item(0);

                if (! $code instanceof DOMElement || ! $this->isMermaid($code)) {
                    continue;
                }

                $this->replace($dom, $pre, trim($code->textContent));
                $found = true;
            }

            if ($found) {
                $body->appendChild($this->bootstrap($dom));
            }
        });
    }

    private function isMermaid(DOMElement $code): bool
    {
        foreach (explode(' ', $code->getAttribute('class')) as $class) {
            if ($class === 'language-mermaid') {
                return true;
            }
        }

        return false;
    }

    private function replace(DOMDocument $dom, DOMElement $pre, string $graph): void
    {
        $parent = $pre->parentNode;

        // @codeCoverageIgnoreStart
        if ($parent === null) {
            return;
        }
        // @codeCoverageIgnoreEnd

        $wrapper = $dom->createElement('div');
        $wrapper->setAttribute('class', 'laradocs-mermaid');
        $wrapper->setAttribute('data-laradocs-mermaid', '');

        // The graph definition doubles as the no-JS fallback. mermaid.run()
        // reads its text content, then swaps it for the rendered SVG.
        $source = $dom->createElement('pre');
        $source->setAttribute('class', 'laradocs-mermaid-source');
        $source->appendChild($dom->createTextNode($graph));
        $wrapper->appendChild($source);

        $parent->replaceChild($wrapper, $pre);
    }

    private function bootstrap(DOMDocument $dom): DOMElement
    {
        // json_encode only fails here on malformed UTF-8; fall back to an empty
        // JS string literal so the loader degrades to the no-JS fallback.
        $src = json_encode($this->src);
        $src = is_string($src) ? $src : '""';

        $script = $dom->createElement('script');
        $script->setAttribute('type', 'module');
        $script->appendChild($dom->createTextNode(
            str_replace('__SRC__', $src, self::BOOTSTRAP)
        ));

        return $script;
    }

    /**
     * Self-contained, idempotent loader. Kept inline (rather than in the shared
     * bundle) so mermaid only ships on pages that contain a diagram.
     */
    private const BOOTSTRAP = <<<'JS'
        (function () {
          if (window.__laradocsMermaid) return;
          window.__laradocsMermaid = true;
          var SRC = __SRC__;

          function resolvedTheme() {
            var t = document.documentElement.getAttribute('data-theme');
            if (t === 'dark' || t === 'light') return t;
            return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
          }

          function loaded() {
            document.querySelectorAll('.laradocs-mermaid').forEach(function (el) {
              el.classList.remove('is-loading');
              // Once an SVG is in place, shed the fallback code-block chrome.
              if (el.querySelector('svg')) el.classList.add('is-rendered');
            });
          }

          function start() {
            var blocks = document.querySelectorAll('.laradocs-mermaid');
            if (!blocks.length) return;

            blocks.forEach(function (el) {
              el.classList.add('is-loading');
              var src = el.querySelector('.laradocs-mermaid-source');
              if (src && !src.hasAttribute('data-src')) src.setAttribute('data-src', src.textContent);
            });

            import(SRC).then(function (mod) {
              var mermaid = mod.default || mod;

              function render() {
                var cs = getComputedStyle(document.documentElement);
                var tok = function (name, fallback) {
                  var v = cs.getPropertyValue(name).trim();
                  return v || fallback;
                };
                // mermaid's colour parser only understands solid colours, so
                // skip computed tokens that resolve to color-mix()/var() etc.
                var color = function (name, fallback) {
                  var v = cs.getPropertyValue(name).trim();
                  return /^(#|rgb|hsl)/i.test(v) ? v : fallback;
                };

                mermaid.initialize({
                  startOnLoad: false,
                  securityLevel: 'strict',
                  theme: resolvedTheme() === 'dark' ? 'dark' : 'default',
                  fontFamily: tok('--dc-font', 'inherit'),
                  themeVariables: {
                    background: color('--dc-bg', '#ffffff'),
                    primaryColor: color('--dc-bg-elev', '#fafafa'),
                    primaryTextColor: color('--dc-fg', '#0a0a0a'),
                    primaryBorderColor: color('--dc-rule-strong', '#cccccc'),
                    lineColor: color('--dc-muted', '#525252'),
                    tertiaryColor: color('--dc-bg', '#ffffff')
                  }
                });

                var nodes = document.querySelectorAll('.laradocs-mermaid-source');
                nodes.forEach(function (n) {
                  if (n.hasAttribute('data-src')) {
                    n.textContent = n.getAttribute('data-src');
                    n.removeAttribute('data-processed');
                  }
                });

                mermaid.run({ nodes: nodes, suppressErrors: true }).then(loaded).catch(loaded);
              }

              render();

              var last = resolvedTheme();
              var maybeRerender = function () {
                var now = resolvedTheme();
                if (now !== last) { last = now; render(); }
              };

              new MutationObserver(maybeRerender).observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['data-theme']
              });

              if (window.matchMedia) {
                var mq = window.matchMedia('(prefers-color-scheme: dark)');
                mq.addEventListener ? mq.addEventListener('change', maybeRerender)
                                    : mq.addListener(maybeRerender);
              }
            }).catch(loaded);
          }

          if (document.readyState !== 'loading') start();
          else document.addEventListener('DOMContentLoaded', start);
        })();
        JS;
}

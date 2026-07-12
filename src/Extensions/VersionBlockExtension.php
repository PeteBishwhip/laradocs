<?php

declare(strict_types=1);

namespace Laradocs\Extensions;

use Laradocs\Contracts\MarkdownExtension;
use Laradocs\Support\Version;
use Laradocs\Support\VersionRegistry;

/**
 * Expands the inline version directives `:::version-since[2.0]`,
 * `:::version-until[2.0]` and `:::version-only[1.0, 1.1]` into a wrapping
 * `<div class="version-block">` that conditionally shows its content for the
 * active docs version.
 *
 * The directive is rewritten before CommonMark runs, using the CommonMark
 * Type-6 HTML-block pattern (blank lines around the inner content) so the
 * wrapped Markdown — headings, lists, code blocks — is still parsed normally.
 *
 * In the default "client" mode every block is emitted with a `hidden`
 * attribute and JavaScript toggles visibility for the selected version. In
 * "server" mode the directive is evaluated against {@see Version::current()}
 * here: matching blocks are emitted without `hidden` and non-matching blocks
 * are stripped entirely.
 */
final class VersionBlockExtension implements MarkdownExtension
{
    /**
     * @readonly
     * @var bool
     */
    private $server = false;
    /**
     * Matches a `:::version-{type}[{spec}]` … `:::` fenced block. The `m` flag
     * anchors the fences to line starts; `s` lets the inner `(.*?)` span lines.
     */
    private const PATTERN = '/^:::version-(since|until|only)\[([^\]]+)\]\s*$(.*?)^:::$/ms';

    public function __construct(bool $server = false)
    {
        $this->server = $server;
    }

    public function processMarkdown(string $markdown): string
    {
        return (string) preg_replace_callback(
            self::PATTERN,
            function (array $m): string {
                return $this->render($m[1], trim($m[2]), trim($m[3], "\n"));
            },
            $markdown,
        );
    }

    /**
     * Render a single directive: in server mode, evaluate it against the current
     * version (stripping non-matching blocks); in client mode, always emit a
     * hidden block for the runtime to toggle.
     */
    private function render(string $type, string $spec, string $inner): string
    {
        if ($this->server) {
            $current = Version::current();

            if ($current === null || ! $this->matches($type, $spec, $current)) {
                return '';
            }

            return $this->wrap($type, $spec, $inner, false);
        }

        return $this->wrap($type, $spec, $inner, true);
    }

    /**
     * Whether the directive applies to the given current version, using the same
     * semver comparator as {@see VersionRegistry}.
     */
    private function matches(string $type, string $spec, string $current): bool
    {
        switch ($type) {
            case 'since':
                return VersionRegistry::compare($current, $spec) >= 0;
            case 'until':
                return VersionRegistry::compare($current, $spec) < 0;
            case 'only':
                return $this->inList($current, $spec);
            default:
                return false;
        }
    }

    /**
     * Whether the current version equals any of the comma-separated handles in a
     * `version-only` spec.
     */
    private function inList(string $current, string $spec): bool
    {
        foreach (explode(',', $spec) as $candidate) {
            if (VersionRegistry::compare($current, trim($candidate)) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Wrap inner Markdown in a Type-6 HTML block. The blank lines around the
     * content keep CommonMark parsing it as Markdown rather than raw HTML.
     */
    private function wrap(string $type, string $spec, string $inner, bool $hidden): string
    {
        return sprintf(
            '<div class="version-block" data-version-%s="%s"%s>%s%s%s</div>',
            $type,
            $spec,
            $hidden ? ' hidden' : '',
            "\n\n",
            $inner,
            "\n\n",
        );
    }
}

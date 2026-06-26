<?php

declare(strict_types=1);

namespace Laradocs\Extensions;

use Laradocs\Contracts\MarkdownExtension;

/**
 * Pre-processes two tab syntaxes before CommonMark runs:
 *
 * 1. Code-tab shorthand — strips `tab:Label` from a fenced code block's info
 *    string and injects `{data-tab="Label"}` so the AttributesExtension
 *    forwards the label to the rendered `<code>` element.
 *
 * 2. Content tabs — rewrites `:::tabs … :::` / `--- Label` container blocks
 *    into Type-6 HTML blocks.  Blank lines around the inner markdown content
 *    tell CommonMark to parse it normally, so callouts, code fences, images
 *    etc. inside a tab all render correctly via the standard pipeline.
 */
final class TabsMarkdownExtension implements MarkdownExtension
{
    private int $counter = 0;

    public function processMarkdown(string $markdown): string
    {
        $markdown = $this->transformCodeTabBlocks($markdown);
        $markdown = $this->transformContentTabs($markdown);

        return $markdown;
    }

    /**
     * Wrap each tab-labelled fenced code block in a Type-6 HTML block div.
     * The blank lines inside the div ensure CommonMark still parses the inner
     * code fence as a regular fenced code block, while the outer div carries
     * the `data-tab` attribute for TabsHtmlExtension to pick up later.
     *
     * Input:
     *   ```php tab:PHP
     *   $x = 1;
     *   ```
     *
     * Output:
     *   <div class="laradocs-code-tab-pending" data-tab="PHP">
     *
     *   ```php
     *   $x = 1;
     *   ```
     *
     *   </div>
     */
    private function transformCodeTabBlocks(string $markdown): string
    {
        return (string) preg_replace_callback(
            '/^(`{3,}|~{3,})([^\n]*\btab:\S+[^\n]*)\n(.*?)^\1[ \t]*$/ms',
            function (array $m): string {
                $fence   = $m[1];
                $info    = $m[2];
                $content = $m[3];

                if (! preg_match('/\btab:(\S+)/', $info, $tm)) {
                    return $m[0];
                }

                $label = $tm[1];

                // Strip the tab:Label token, preserving the rest of the info string.
                $info = trim((string) preg_replace('/\s*\btab:\S+/', '', $info));

                $escaped = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

                // The blank lines around the code fence inside the div are required
                // for CommonMark to treat the fence as a block-level element rather
                // than raw HTML content.
                return "\n<div class=\"laradocs-code-tab-pending\" data-tab=\"{$escaped}\">\n\n"
                    . $fence . $info . "\n"
                    . $content
                    . $fence . "\n"
                    . "\n</div>\n";
            },
            $markdown,
        );
    }

    /**
     * Convert `:::tabs … :::` / `--- Label` blocks into Type-6 HTML block
     * wrappers so that CommonMark still parses the inner markdown normally.
     *
     * The blank lines before and after each tab section's content are load-
     * bearing: they tell CommonMark that what follows the `<div>` line is
     * regular markdown, not raw HTML.
     */
    private function transformContentTabs(string $markdown): string
    {
        return (string) preg_replace_callback(
            '/^:::[ \t]+tabs((?:[ \t][^\n]*)?)(\n.*?)^:::[ \t]*$/ms',
            function (array $m): string {
                $attrs = trim($m[1]);
                $inner = $m[2];

                // Optional group="..." attribute on the opening fence.
                $group = 'content';
                if (preg_match('/\bgroup=["\']?([^"\'>\s]+)["\']?/', $attrs, $gm)) {
                    $group = $gm[1];
                }
                $escapedGroup = htmlspecialchars($group, ENT_QUOTES, 'UTF-8');

                // Split by `--- Label` separators at line start. Filter whitespace-only
                // parts: the content after the opening fence starts with a newline,
                // creating a phantom empty first element before the first --- separator.
                $parts = preg_split('/^---[ \t]+/m', $inner, -1, PREG_SPLIT_NO_EMPTY);
                $parts = array_values(array_filter($parts, fn (string $p): bool => trim($p) !== ''));

                if (empty($parts)) {
                    return '';
                }

                $out = "\n<div class=\"laradocs-tab-group-raw\" data-group=\"{$escapedGroup}\">\n";

                foreach ($parts as $part) {
                    $eol   = strpos($part, "\n");
                    $label = $eol !== false ? trim(substr($part, 0, $eol)) : trim($part);
                    $body  = $eol !== false ? rtrim(substr($part, $eol + 1)) : '';

                    $escapedLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

                    // The double-newline after the opening tag and before the
                    // closing tag are what make CommonMark treat $body as markdown.
                    $out .= "\n<div class=\"laradocs-tab-raw\" data-tab=\"{$escapedLabel}\">\n\n";
                    $out .= $body;
                    $out .= "\n\n</div>\n";
                }

                $out .= "\n</div>\n";

                return $out;
            },
            $markdown,
        );
    }
}

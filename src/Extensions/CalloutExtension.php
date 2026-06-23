<?php

declare(strict_types=1);

namespace Laradocs\Extensions;

use DOMDocument;
use DOMElement;
use Laradocs\Contracts\HtmlExtension;
use Laradocs\Support\Html;

/**
 * Converts GitHub-style alert blockquotes — `> [!NOTE]` — into styled callouts.
 *
 * Titles are resolved via the laradocs translation files so they follow the
 * active docs locale. An optional inline title placed immediately after the
 * marker (`> [!NOTE] Custom title`) overrides the translated default.
 */
final class CalloutExtension implements HtmlExtension
{
    /**
     * @var list<string>
     */
    private const KNOWN_TYPES = ['note', 'tip', 'important', 'warning', 'danger', 'caution'];

    public function processHtml(string $html): string
    {
        return Html::mutate($html, function (DOMDocument $dom, DOMElement $body): void {
            /** @var DOMElement $blockquote */
            foreach (iterator_to_array($body->getElementsByTagName('blockquote')) as $blockquote) {
                $this->transform($blockquote);
            }
        });
    }

    private function transform(DOMElement $blockquote): void
    {
        $inner = Html::innerHtml($blockquote);

        if (preg_match('/\[!(\w+)\]/i', $inner, $matches) !== 1) {
            return;
        }

        $type = strtolower($matches[1]);

        if (! in_array($type, self::KNOWN_TYPES, true)) {
            return;
        }

        // An optional inline title may appear right after the marker, e.g. `> [!NOTE] Custom title`.
        $title = null;
        if (preg_match(
            '/\[!' . preg_quote($matches[1], '/') . '\][ \t]+([^\n<\[]+?)[ \t]*(?=\n|<br|\s*<\/p>)/i',
            $inner,
            $titleMatches
        )) {
            $title = trim($titleMatches[1]);
        }

        if ($title === null || $title === '') {
            $title = (string) __('laradocs::laradocs.callouts.' . $type);
        }

        // Remove the marker line, including any inline title text and trailing <br>.
        $body = preg_replace(
            '/\[!' . preg_quote($matches[1], '/') . '\][^\n<\[]*\s*(<br\s*\/?>)?\s*/i',
            '',
            $inner,
            1
        ) ?? $inner;

        $replacement = sprintf(
            '<div class="laradocs-callout laradocs-callout-%s" role="note">'
            . '<div class="laradocs-callout-title">%s</div>'
            . '<div class="laradocs-callout-body">%s</div></div>',
            $type,
            $title,
            trim($body)
        );

        Html::replaceWithHtml($blockquote, $replacement);
    }
}

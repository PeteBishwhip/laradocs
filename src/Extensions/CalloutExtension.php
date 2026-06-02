<?php

declare(strict_types=1);

namespace Laradocs\Extensions;

use DOMDocument;
use DOMElement;
use Laradocs\Contracts\HtmlExtension;
use Laradocs\Support\Html;

/**
 * Converts GitHub-style alert blockquotes — `> [!NOTE]` — into styled callouts.
 */
final class CalloutExtension implements HtmlExtension
{
    /**
     * @var array<string, string>
     */
    private const TITLES = [
        'note' => 'Note',
        'tip' => 'Tip',
        'important' => 'Important',
        'warning' => 'Warning',
        'danger' => 'Danger',
        'caution' => 'Caution',
    ];

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

        if (! isset(self::TITLES[$type])) {
            return;
        }

        $body = preg_replace(
            '/\[!' . preg_quote($matches[1], '/') . '\]\s*(<br\s*\/?>)?\s*/i',
            '',
            $inner,
            1
        ) ?? $inner;

        $replacement = sprintf(
            '<div class="laradocs-callout laradocs-callout-%s" role="note">'
            . '<div class="laradocs-callout-title">%s</div>'
            . '<div class="laradocs-callout-body">%s</div></div>',
            $type,
            self::TITLES[$type],
            trim($body)
        );

        Html::replaceWithHtml($blockquote, $replacement);
    }
}

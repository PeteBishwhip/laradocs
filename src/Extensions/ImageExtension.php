<?php

declare(strict_types=1);

namespace Laradocs\Extensions;

use DOMDocument;
use DOMElement;
use Laradocs\Contracts\HtmlExtension;
use Laradocs\Support\Html;

/**
 * Enhances images with lazy-loading, async decoding and optional captions.
 */
final class ImageExtension implements HtmlExtension
{
    public function processHtml(string $html): string
    {
        return Html::mutate($html, function (DOMDocument $dom, DOMElement $body): void {
            /** @var DOMElement $img */
            foreach (iterator_to_array($body->getElementsByTagName('img')) as $img) {
                $this->enhance($dom, $img);
            }
        });
    }

    private function enhance(DOMDocument $dom, DOMElement $img): void
    {
        if (! $img->hasAttribute('loading')) {
            $img->setAttribute('loading', 'lazy');
        }

        $img->setAttribute('decoding', 'async');
        $img->setAttribute('class', trim($img->getAttribute('class') . ' laradocs-image'));
        $img->setAttribute('data-zoomable', 'true');

        $caption = $img->getAttribute('title');
        $parent = $img->parentNode;

        if ($caption === '' || ! $parent instanceof DOMElement || strtolower($parent->nodeName) === 'figure') {
            return;
        }

        $figure = $dom->createElement('figure');
        $figure->setAttribute('class', 'laradocs-figure');
        $parent->replaceChild($figure, $img);
        $figure->appendChild($img);

        $figcaption = $dom->createElement('figcaption');
        $figcaption->textContent = $caption;
        $figure->appendChild($figcaption);
    }
}

<?php

declare(strict_types=1);

namespace Laradocs\Extensions;

use DOMDocument;
use DOMElement;
use Illuminate\Support\Str;
use Laradocs\Contracts\HtmlExtension;
use Laradocs\Support\Html;

/**
 * Adds stable id attributes and hover permalinks to headings.
 */
final class HeadingAnchorExtension implements HtmlExtension
{
    public function processHtml(string $html): string
    {
        return Html::mutate($html, function (DOMDocument $dom, DOMElement $body): void {
            $used = [];

            foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $tag) {
                /** @var DOMElement $heading */
                foreach (iterator_to_array($body->getElementsByTagName($tag)) as $heading) {
                    $this->anchor($dom, $heading, $used);
                }
            }
        });
    }

    /**
     * @param  array<string, int>  $used
     */
    private function anchor(DOMDocument $dom, DOMElement $heading, array &$used): void
    {
        $id = $heading->getAttribute('id');

        if ($id === '') {
            $id = $this->uniqueSlug($heading->textContent, $used);
            $heading->setAttribute('id', $id);
        }

        $heading->setAttribute('class', trim($heading->getAttribute('class') . ' laradocs-heading'));

        $link = $dom->createElement('a', '#');
        $link->setAttribute('href', '#' . $id);
        $link->setAttribute('class', 'laradocs-anchor');
        $link->setAttribute('aria-hidden', 'true');

        $heading->insertBefore($link, $heading->firstChild);
    }

    /**
     * @param  array<string, int>  $used
     */
    private function uniqueSlug(string $text, array &$used): string
    {
        $slug = Str::slug($text);

        if ($slug === '') {
            $slug = 'section';
        }

        if (isset($used[$slug])) {
            $used[$slug]++;

            return $slug . '-' . $used[$slug];
        }

        $used[$slug] = 0;

        return $slug;
    }
}

<?php

declare(strict_types=1);

namespace Laradocs\Support;

use Closure;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Small DOM helper for post-processing rendered markdown HTML fragments.
 */
final class Html
{
    /**
     * Mutate an HTML fragment via a callback operating on the wrapper element,
     * returning the serialized inner HTML.
     *
     * @param  Closure(DOMDocument, DOMElement): void  $callback
     */
    public static function mutate(string $html, Closure $callback): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $dom = self::load($html);
        $body = self::body($dom);

        // @codeCoverageIgnoreStart
        if (! $body instanceof DOMElement) {
            return $html;
        }
        // @codeCoverageIgnoreEnd

        $callback($dom, $body);

        return self::innerHtml($body);
    }

    public static function load(string $html): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');

        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="utf-8" ?><body>' . $html . '</body>',
            LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $dom;
    }

    public static function body(DOMDocument $dom): ?DOMElement
    {
        $body = $dom->getElementsByTagName('body')->item(0);

        return $body instanceof DOMElement ? $body : null;
    }

    public static function innerHtml(DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument?->saveHTML($child) ?? '';
        }

        return $html;
    }

    /**
     * Parse an HTML string into detached nodes belonging to $dom.
     *
     * @return array<int, DOMNode>
     */
    public static function fragment(DOMDocument $dom, string $html): array
    {
        $fragmentDom = self::load($html);
        $body = self::body($fragmentDom);

        // @codeCoverageIgnoreStart
        if (! $body instanceof DOMElement) {
            return [];
        }
        // @codeCoverageIgnoreEnd

        $nodes = [];

        foreach (iterator_to_array($body->childNodes) as $child) {
            $nodes[] = $dom->importNode($child, true);
        }

        return $nodes;
    }

    /**
     * Replace a node with parsed HTML in its place.
     */
    public static function replaceWithHtml(DOMElement $node, string $html): void
    {
        $dom = $node->ownerDocument;
        $parent = $node->parentNode;

        // @codeCoverageIgnoreStart
        if (! $dom instanceof DOMDocument || $parent === null) {
            return;
        }
        // @codeCoverageIgnoreEnd

        foreach (self::fragment($dom, $html) as $new) {
            $parent->insertBefore($new, $node);
        }

        $parent->removeChild($node);
    }
}

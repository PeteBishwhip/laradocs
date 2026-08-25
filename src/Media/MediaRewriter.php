<?php

declare(strict_types=1);

namespace Laradocs\Media;

use DOMDocument;
use DOMElement;
use Laradocs\Documents\Document;
use Laradocs\Support\Html;

/**
 * Points the media in a rendered page at the media route.
 *
 * This runs on the way out of the HTML cache rather than inside it, for two
 * reasons. The document is known here, so a relative source resolves against
 * the page that wrote it, which a markdown or HTML extension cannot do. And a
 * signed URL stays out of the cache, so it can carry a per-request expiry
 * without every reader sharing one signature.
 */
final class MediaRewriter
{
    /**
     * Attributes that carry a media source, by tag.
     *
     * @var array<string, list<string>>
     */
    private const ATTRIBUTES = [
        'img' => ['src'],
        'source' => ['src'],
        'video' => ['src', 'poster'],
        'audio' => ['src'],
        'embed' => ['src'],
    ];

    public function __construct(
        private readonly MediaSource $source,
    ) {}

    public function rewrite(string $html, ?Document $document = null): string
    {
        if (! $this->source->enabled() || $html === '') {
            return $html;
        }

        return Html::mutate($html, function (DOMDocument $dom, DOMElement $body) use ($document): void {
            foreach (self::ATTRIBUTES as $tag => $attributes) {
                /** @var DOMElement $element */
                foreach (iterator_to_array($body->getElementsByTagName($tag)) as $element) {
                    foreach ($attributes as $attribute) {
                        $this->point($element, $attribute, $document);
                    }
                }
            }
        });
    }

    private function point(DOMElement $element, string $attribute, ?Document $document): void
    {
        $value = $element->getAttribute($attribute);

        if ($value === '') {
            return;
        }

        $path = $this->source->resolve($value, $document);

        if ($path !== null) {
            $element->setAttribute($attribute, MediaUrl::for($path));
        }
    }
}

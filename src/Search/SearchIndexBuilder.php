<?php

declare(strict_types=1);

namespace Laradocs\Search;

use Closure;
use Laradocs\Documents\Document;
use Laradocs\Documents\DocumentCollection;
use Laradocs\Support\Html;

/**
 * Turns a collection of documents into a flat, serialisable search index:
 * one entry per visible, searchable page with its title, group and the
 * plain-text body extracted from the rendered HTML.
 */
final class SearchIndexBuilder
{
    /**
     * @param  DocumentCollection<int, Document>  $documents
     * @param  Closure(Document): string  $render  Renders a document to HTML.
     * @return array<int, array{slug: string, title: string, group: string, content: string}>
     */
    public function build(DocumentCollection $documents, Closure $render, int $maxChars = 10000): array
    {
        $entries = [];

        $searchable = $documents
            ->visible()
            ->filter(fn (Document $document): bool => $document->isSearchable())
            ->ordered();

        foreach ($searchable as $document) {
            $entries[] = [
                'slug' => $document->slug,
                'title' => $document->title(),
                'group' => $document->group() ?? '',
                'content' => $this->content($render($document), $maxChars),
            ];
        }

        return $entries;
    }

    private function content(string $html, int $maxChars): string
    {
        $text = Html::toText($html);

        if ($maxChars > 0 && mb_strlen($text) > $maxChars) {
            $text = mb_substr($text, 0, $maxChars);
        }

        return $text;
    }
}

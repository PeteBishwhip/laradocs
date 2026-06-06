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
     * @param  array<int, string>  $exclude  fnmatch slug patterns to exclude from the index.
     * @param  array<int, string>  $include  fnmatch slug patterns to include; when non-empty,
     *                                       only matching slugs are indexed.
     * @param  array<string, float>  $ranks  fnmatch slug patterns mapped to rank multipliers.
     *                                       First matching pattern wins; combined with the
     *                                       page's own search_rank front-matter value.
     * @return array<int, array{slug: string, title: string, group: string, content: string, rank: float}>
     */
    public function build(
        DocumentCollection $documents,
        Closure $render,
        int $maxChars = 10000,
        array $exclude = [],
        array $include = [],
        array $ranks = [],
    ): array {
        $entries = [];

        $searchable = $documents
            ->visible()
            ->filter(fn (Document $document): bool => $document->isSearchable())
            ->filter(fn (Document $document): bool => $this->allowed($document->slug, $exclude, $include))
            ->ordered();

        foreach ($searchable as $document) {
            $entries[] = [
                'slug' => $document->slug,
                'title' => $document->title(),
                'group' => $document->group() ?? '',
                'content' => $this->content($render($document), $maxChars),
                'rank' => $this->rank($document->slug, $document->searchRank(), $ranks),
            ];
        }

        return $entries;
    }

    /**
     * Whether a slug is permitted by the exclude/include lists.
     *
     * @param  array<int, string>  $exclude
     * @param  array<int, string>  $include
     */
    private function allowed(string $slug, array $exclude, array $include): bool
    {
        $isExcluded = array_filter($exclude, fn (string $p): bool => fnmatch($p, $slug)) !== [];

        if ($isExcluded) {
            return false;
        }

        return $include === []
            || array_filter($include, fn (string $p): bool => fnmatch($p, $slug)) !== [];
    }

    /**
     * Compute the final rank multiplier for a slug by combining the page's own
     * search_rank front-matter value with the first matching config pattern.
     *
     * @param  array<string, float>  $ranks
     */
    private function rank(string $slug, float $pageRank, array $ranks): float
    {
        $patternRank = 1.0;

        foreach ($ranks as $pattern => $multiplier) {
            if (fnmatch($pattern, $slug)) {
                $patternRank = $multiplier;
                break;
            }
        }

        return max(0.0, $pageRank * $patternRank);
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

<?php

declare(strict_types=1);

namespace Laradocs\Search;

use Laradocs\Search\Contracts\SearchEngine;

/**
 * The zero-dependency fallback engine. Ranks the pre-rendered index in-process
 * with simple weighted term matching: title hits outweigh body hits, and every
 * query term must appear somewhere in a page for it to match.
 */
final class JsonSearchEngine implements SearchEngine
{
    public function search(string $query, array $index, int $limit): array
    {
        $terms = $this->terms($query);

        if ($terms === []) {
            return [];
        }

        $scored = [];

        foreach ($index as $entry) {
            $title = mb_strtolower($entry['title']);
            $content = mb_strtolower($entry['content']);

            $score = 0;
            $matchesAll = true;

            foreach ($terms as $term) {
                $inTitle = str_contains($title, $term);
                $inContent = str_contains($content, $term);

                if (! $inTitle && ! $inContent) {
                    $matchesAll = false;

                    break;
                }

                $score += ($inTitle ? 3 : 0) + ($inContent ? 1 : 0);
            }

            if ($matchesAll) {
                $scored[] = ['score' => $score, 'entry' => $entry];
            }
        }

        usort($scored, fn (array $a, array $b): int => $b['score'] <=> $a['score']
            ?: strcmp($a['entry']['title'], $b['entry']['title']));

        return array_map(
            fn (array $row): array => $row['entry'],
            array_slice($scored, 0, $limit)
        );
    }

    public function sync(array $index): void
    {
        // The index is read directly at query time; nothing to push.
    }

    public function flush(): void
    {
        // Nothing is stored outside the cached index.
    }

    public function name(): string
    {
        return 'json';
    }

    /**
     * @return array<int, string>
     */
    private function terms(string $query): array
    {
        $terms = preg_split('/\s+/u', mb_strtolower(trim($query))) ?: [];

        return array_values(array_filter($terms, fn (string $term): bool => $term !== ''));
    }
}

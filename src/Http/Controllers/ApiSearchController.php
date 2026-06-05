<?php

declare(strict_types=1);

namespace Laradocs\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laradocs\Laradocs;
use Laradocs\Routing\DocumentUrl;
use Laradocs\Search\Contracts\SearchEngine;
use Laradocs\Support\Config;

final class ApiSearchController
{
    private const EXCERPT_LENGTH = 160;

    public function __construct(
        private readonly Laradocs $laradocs,
        private readonly SearchEngine $engine,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $raw = $request->query('q', '');
        $query = is_string($raw) ? trim($raw) : '';

        if (mb_strlen($query) < Config::int('laradocs.search.min_chars', 2)) {
            return new JsonResponse(['version' => 1, 'data' => []]);
        }

        $results = $this->engine->search(
            $query,
            $this->laradocs->searchIndex(),
            Config::int('laradocs.search.limit', 20),
        );

        return new JsonResponse([
            'version' => 1,
            'data' => array_map(fn (array $entry): array => [
                'slug' => $entry['slug'],
                'title' => $entry['title'],
                'url' => DocumentUrl::toSlug($entry['slug']),
                'group' => $entry['group'],
                'excerpt' => $this->excerpt($entry['content'], $query),
            ], $results),
        ]);
    }

    private function excerpt(string $content, string $query): string
    {
        if ($content === '') {
            return '';
        }

        $term = $this->firstTerm($query);
        $position = $term === '' ? false : mb_stripos($content, $term);

        if ($position === false) {
            return Str::limit($content, self::EXCERPT_LENGTH);
        }

        $start = max(0, $position - 40);
        $snippet = trim(mb_substr($content, $start, self::EXCERPT_LENGTH));

        $prefix = $start > 0 ? '…' : '';
        $suffix = $start + self::EXCERPT_LENGTH < mb_strlen($content) ? '…' : '';

        return $prefix . $snippet . $suffix;
    }

    private function firstTerm(string $query): string
    {
        $terms = preg_split('/\s+/u', $query) ?: [];

        return (string) ($terms[0] ?? '');
    }
}

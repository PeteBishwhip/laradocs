<?php

declare(strict_types=1);

namespace Laradocs\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laradocs\Laradocs;
use Laradocs\Routing\DocumentUrl;
use Laradocs\Search\Contracts\SearchEngine;
use Laradocs\Search\Excerpt;
use Laradocs\Support\Config;

final class ApiSearchController
{
    public function __construct(
        private readonly Laradocs $laradocs,
        private readonly SearchEngine $engine,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $raw = $request->query('q', '');
        $query = is_string($raw) ? trim($raw) : '';

        if (mb_strlen($query) < Config::int('laradocs.search.min_chars', 2)) {
            return $this->envelope($request, []);
        }

        $results = $this->engine->search(
            $query,
            $this->laradocs->searchIndex(),
            Config::int('laradocs.search.limit', 20),
        );

        $data = array_map(fn (array $entry): array => [
            'type' => 'page',
            'id' => $entry['slug'] === '' ? '_root' : $entry['slug'],
            'attributes' => [
                'title' => $entry['title'],
                'slug' => $entry['slug'],
                'url' => DocumentUrl::toSlug($entry['slug']),
                'group' => $entry['group'],
                'excerpt' => Excerpt::make($entry['content'], $query),
            ],
        ], $results);

        return $this->envelope($request, $data);
    }

    /**
     * @param  array<int, array<string, mixed>>  $data
     */
    private function envelope(Request $request, array $data): JsonResponse
    {
        return (new JsonResponse([
            'jsonapi' => ['version' => '1.0'],
            'links' => ['self' => $request->fullUrl()],
            'data' => $data,
        ]))->header('Content-Type', 'application/vnd.api+json');
    }
}

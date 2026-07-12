<?php

declare(strict_types=1);

namespace Laradocs\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laradocs\Laradocs;
use Laradocs\Routing\DocumentUrl;
use Laradocs\Search\Contracts\SearchEngine;
use Laradocs\Search\Excerpt;
use Laradocs\Support\Config;

/**
 * JSON search endpoint backing the ⌘K command palette. Works identically
 * whether the active engine is the local JSON index or a Scout backend.
 */
final class SearchController
{
    /**
     * @readonly
     * @var \Laradocs\Laradocs
     */
    private $laradocs;
    /**
     * @readonly
     * @var \Laradocs\Search\Contracts\SearchEngine
     */
    private $engine;
    public function __construct(Laradocs $laradocs, SearchEngine $engine)
    {
        $this->laradocs = $laradocs;
        $this->engine = $engine;
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (! Config::bool('laradocs.ui.search.enabled', true)) {
            abort(404);
        }

        $raw = $request->query('q', '');
        $query = is_string($raw) ? trim($raw) : '';

        if (mb_strlen($query) < Config::int('laradocs.search.min_chars', 2)) {
            return new JsonResponse(['results' => []]);
        }

        $results = $this->engine->search(
            $query,
            $this->laradocs->searchIndex(),
            Config::int('laradocs.search.limit', 20),
        );

        return new JsonResponse([
            'results' => array_map(function (array $entry) use ($query): array {
                return [
                    'slug' => $entry['slug'],
                    'title' => $entry['title'],
                    'group' => $entry['group'],
                    'breadcrumb' => $this->breadcrumb($entry['slug'], $entry['group']),
                    'url' => DocumentUrl::toSlug($entry['slug']),
                    'excerpt' => Excerpt::make($entry['content'], $query),
                ];
            }, $results),
        ]);
    }

    /**
     * The trail of ancestor sections a page lives under, used by the palette to
     * render a breadcrumb and to group hits. Built from the slug's parent
     * segments (the leaf is the page itself), humanised for display. An explicit
     * `group:` supersedes the humanised top-level segment so authored section
     * names win over the path. A top-level page collapses to just its group, or
     * to an empty trail when it has neither ancestors nor a group.
     *
     * @return array<int, string>
     */
    private function breadcrumb(string $slug, string $group): array
    {
        $segments = array_values(array_filter(
            explode('/', $slug),
            static function (string $segment): bool {
                return $segment !== '';
            },
        ));

        array_pop($segments);

        $crumbs = array_map(static function (string $segment): string {
            return Str::headline($segment);
        }, $segments);

        if ($group === '') {
            return $crumbs;
        }

        if ($crumbs === []) {
            return [$group];
        }

        $crumbs[0] = $group;

        return $crumbs;
    }
}

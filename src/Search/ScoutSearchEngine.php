<?php

declare(strict_types=1);

namespace Laradocs\Search;

use Illuminate\Support\Collection;
use Laradocs\Search\Contracts\SearchEngine;
use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;

/**
 * Delegates search to whichever Scout engine the host application has
 * configured (Meilisearch, Typesense, Algolia, …). Documents are indexed via
 * {@see SearchableDocument} stand-ins, and query results are mapped back onto
 * the pre-rendered index so display data stays consistent across engines.
 *
 * This class is only ever loaded when laravel/scout is installed and selected,
 * so referencing Scout's classes here is safe.
 */
final class ScoutSearchEngine implements SearchEngine
{
    public function __construct(
        private readonly EngineManager $engines,
        private readonly string $index,
    ) {}

    public function search(string $query, array $index, int $limit): array
    {
        // SearchableDocument intentionally stands in for an Eloquent model;
        // Scout only calls the lightweight surface it implements.
        // @phpstan-ignore argument.type
        $builder = new Builder($this->prototype(), $query);
        $builder->limit = $limit;

        /** @var array<int, string> $keys */
        $keys = $this->engines->engine()->keys($builder)->all();

        $bySlug = [];

        foreach ($index as $entry) {
            $bySlug[$entry['slug']] = $entry;
        }

        $results = [];

        foreach ($keys as $key) {
            if (isset($bySlug[$key])) {
                $results[] = $bySlug[$key];
            }
        }

        return $results;
    }

    public function sync(array $index): void
    {
        $engine = $this->engines->engine();
        // @phpstan-ignore argument.type
        $engine->flush($this->prototype());

        $documents = array_map(fn (array $entry): SearchableDocument => new SearchableDocument(
            $this->index,
            $entry['slug'],
            $entry['title'],
            $entry['content'],
            $entry['group'],
        ), $index);

        if ($documents !== []) {
            // @phpstan-ignore argument.type
            $engine->update(new Collection($documents));
        }
    }

    public function flush(): void
    {
        // @phpstan-ignore argument.type
        $this->engines->engine()->flush($this->prototype());
    }

    public function name(): string
    {
        return 'scout';
    }

    private function prototype(): SearchableDocument
    {
        return new SearchableDocument($this->index);
    }
}

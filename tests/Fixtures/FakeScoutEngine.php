<?php

declare(strict_types=1);

namespace Laradocs\Tests\Fixtures;

use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;

/**
 * An in-memory Scout engine for tests: it records indexed documents and runs a
 * naive substring match so the Scout-backed search path can be exercised
 * without a real Meilisearch / Typesense / Algolia service.
 */
final class FakeScoutEngine extends Engine
{
    /**
     * @var array<int, array<string, mixed>>
     */
    public array $documents = [];

    public int $flushed = 0;

    /**
     * @param  Collection<int, mixed>  $models
     */
    public function update($models): void
    {
        foreach ($models as $model) {
            $this->documents[] = array_merge(
                [$model->getScoutKeyName() => $model->getScoutKey()],
                $model->toSearchableArray(),
            );
        }
    }

    /**
     * @param  Collection<int, mixed>  $models
     */
    public function delete($models): void
    {
        //
    }

    public function search(Builder $builder): mixed
    {
        $query = mb_strtolower($builder->query);

        $hits = array_values(array_filter($this->documents, function (array $doc) use ($query): bool {
            $haystack = mb_strtolower(($doc['title'] ?? '') . ' ' . ($doc['content'] ?? ''));

            return $query === '' || str_contains($haystack, $query);
        }));

        if ($builder->limit !== null) {
            $hits = array_slice($hits, 0, $builder->limit);
        }

        return ['hits' => $hits];
    }

    public function paginate(Builder $builder, $perPage, $page): mixed
    {
        return $this->search($builder);
    }

    public function mapIds($results): Collection
    {
        return collect($results['hits'])->pluck('slug');
    }

    public function map(Builder $builder, $results, $model): Collection
    {
        return collect($results['hits']);
    }

    public function lazyMap(Builder $builder, $results, $model): LazyCollection
    {
        return LazyCollection::make($results['hits']);
    }

    public function getTotalCount($results): int
    {
        return count($results['hits']);
    }

    public function flush($model): void
    {
        $this->documents = [];
        $this->flushed++;
    }

    public function createIndex($name, array $options = []): void
    {
        //
    }

    public function deleteIndex($name): void
    {
        //
    }
}

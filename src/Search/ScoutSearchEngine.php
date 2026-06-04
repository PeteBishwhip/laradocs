<?php

declare(strict_types=1);

namespace Laradocs\Search;

use Illuminate\Support\Collection;
use Laradocs\Search\Contracts\SearchEngine;
use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use RuntimeException;

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

        $byScoutKey = [];

        foreach ($index as $entry) {
            $byScoutKey[SearchableDocument::scoutKeyFor($entry['slug'])] = $entry;
        }

        $results = [];

        foreach ($keys as $key) {
            if (isset($byScoutKey[$key])) {
                $results[] = $byScoutKey[$key];
            }
        }

        return $results;
    }

    public function sync(array $index): void
    {
        $engine = $this->engines->engine();

        // Capture Meilisearch's last task UID *before* we touch the index so
        // we can later distinguish tasks created by this sync from leftover
        // failed tasks on the index from previous runs.
        $baselineTaskUid = $this->latestTaskUid($engine);

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

        $this->awaitMeilisearchTasks($engine, $baselineTaskUid);
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

    /**
     * Meilisearch processes indexing tasks asynchronously, so update() can
     * return successfully even though the documents are later rejected
     * (e.g. by primary-key validation). We block on pending tasks and throw
     * if any failed, so callers like laradocs:index can surface the error.
     *
     * Algolia and Typesense fail synchronously via SDK exceptions, so they
     * need no equivalent treatment here.
     */
    private function awaitMeilisearchTasks(object $scoutEngine, ?int $baselineTaskUid): void
    {
        $client = $this->meilisearchClient($scoutEngine);

        if ($client === null) {
            return;
        }

        $this->waitForPendingTasks($client);
        $this->assertNoNewFailedTasks($client, $baselineTaskUid);
    }

    private function latestTaskUid(object $scoutEngine): ?int
    {
        $client = $this->meilisearchClient($scoutEngine);

        if ($client === null) {
            return null;
        }

        /** @var array<int, array<string, mixed>> $tasks */
        $tasks = $this->taskResults($client, [
            'indexUids' => [$this->index],
            'limit' => 1,
        ]);

        if ($tasks === []) {
            return -1;
        }

        return is_int($tasks[0]['uid'] ?? null) ? $tasks[0]['uid'] : -1;
    }

    private function waitForPendingTasks(object $client): void
    {
        /** @var array<int, array<string, mixed>> $pending */
        $pending = $this->taskResults($client, [
            'indexUids' => [$this->index],
            'statuses' => ['enqueued', 'processing'],
            'limit' => 100,
        ]);

        foreach ($pending as $task) {
            if (is_int($task['uid'] ?? null)) {
                $client->waitForTask($task['uid']);
            }
        }
    }

    private function assertNoNewFailedTasks(object $client, ?int $baselineTaskUid): void
    {
        /** @var array<int, array<string, mixed>> $failed */
        $failed = $this->taskResults($client, [
            'indexUids' => [$this->index],
            'statuses' => ['failed'],
            'limit' => 5,
        ]);

        $newFailures = array_values(array_filter(
            $failed,
            fn (array $task): bool => is_int($task['uid'] ?? null)
                && ($baselineTaskUid === null || $task['uid'] > $baselineTaskUid),
        ));

        if ($newFailures === []) {
            return;
        }

        $messages = array_map(
            fn (array $task): string => sprintf(
                '%s [%s]',
                is_string($task['error']['message'] ?? null) ? $task['error']['message'] : 'unknown error',
                is_string($task['type'] ?? null) ? $task['type'] : 'unknown task',
            ),
            $newFailures,
        );

        throw new RuntimeException(
            'Meilisearch rejected indexing tasks: ' . implode('; ', $messages)
        );
    }

    /**
     * Scout's MeilisearchEngine exposes its SDK client as a public `meilisearch`
     * property. We duck-type rather than type-hint so the codebase doesn't take
     * a hard dependency on the optional meilisearch/meilisearch-php package.
     */
    private function meilisearchClient(object $scoutEngine): ?object
    {
        if (! property_exists($scoutEngine, 'meilisearch')) {
            return null;
        }

        $client = $scoutEngine->meilisearch;

        if (! is_object($client) || ! method_exists($client, 'getTasks') || ! method_exists($client, 'waitForTask')) {
            return null;
        }

        return $client;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    private function taskResults(object $client, array $query): array
    {
        /** @var object $result */
        $result = $client->getTasks($query);

        if (! method_exists($result, 'getResults')) {
            return [];
        }

        /** @var array<int, array<string, mixed>> $results */
        $results = $result->getResults();

        return $results;
    }
}

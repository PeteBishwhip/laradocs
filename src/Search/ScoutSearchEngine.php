<?php

declare(strict_types=1);

namespace Laradocs\Search;

use Illuminate\Support\Collection;
use Laradocs\Exceptions\MeilisearchIndexingException;
use Laradocs\Search\Contracts\SearchEngine;
use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use Meilisearch\Client as MeilisearchClient;
use Meilisearch\Contracts\TasksQuery;
use ReflectionProperty;

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

        $tasks = $this->taskResults($client, [
            'indexUids' => [$this->index],
            'limit' => 1,
        ]);

        if ($tasks === []) {
            return -1;
        }

        return is_int($tasks[0]['uid'] ?? null) ? $tasks[0]['uid'] : -1;
    }

    private function waitForPendingTasks(MeilisearchClient $client): void
    {
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

    private function assertNoNewFailedTasks(MeilisearchClient $client, ?int $baselineTaskUid): void
    {
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
                $this->failedTaskMessage($task),
                is_string($task['type'] ?? null) ? $task['type'] : 'unknown task',
            ),
            $newFailures,
        );

        throw MeilisearchIndexingException::rejectedTasks($messages);
    }

    /**
     * @param  array<string, mixed>  $task
     */
    private function failedTaskMessage(array $task): string
    {
        $error = $task['error'] ?? null;

        if (is_array($error) && is_string($error['message'] ?? null)) {
            return $error['message'];
        }

        return 'unknown error';
    }

    /**
     * Scout's MeilisearchEngine stores its SDK client on a `protected
     * $meilisearch` property, so we read it via reflection rather than direct
     * access. The Meilisearch\Client reference is safe: the only way for a
     * Scout engine to hold this property is for the SDK to be installed.
     */
    private function meilisearchClient(object $scoutEngine): ?MeilisearchClient
    {
        if (! property_exists($scoutEngine, 'meilisearch')) {
            return null;
        }

        $client = (new ReflectionProperty($scoutEngine, 'meilisearch'))->getValue($scoutEngine);

        return $client instanceof MeilisearchClient ? $client : null;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    private function taskResults(MeilisearchClient $client, array $query): array
    {
        $result = $client->getTasks($this->buildTasksQuery($query));

        /** @var array<int, array<string, mixed>> $results */
        $results = $result->getResults();

        return $results;
    }

    /**
     * Meilisearch SDK ≥1.0 requires a TasksQuery DTO rather than a raw array.
     * The DTO ships with meilisearch/meilisearch-php, which is always present
     * here: callers only reach this method after we've already resolved a
     * `MeilisearchClient`, which can't exist without the SDK installed.
     *
     * @param  array<string, mixed>  $query
     */
    private function buildTasksQuery(array $query): TasksQuery
    {
        $tasksQuery = new TasksQuery;

        if (isset($query['indexUids']) && is_array($query['indexUids'])) {
            $tasksQuery->setIndexUids($query['indexUids']);
        }

        if (isset($query['statuses']) && is_array($query['statuses'])) {
            $tasksQuery->setStatuses($query['statuses']);
        }

        if (isset($query['limit']) && is_int($query['limit'])) {
            $tasksQuery->setLimit($query['limit']);
        }

        return $tasksQuery;
    }
}

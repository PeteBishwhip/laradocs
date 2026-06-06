<?php

declare(strict_types=1);

namespace Laradocs\Search\Contracts;

/**
 * A pluggable search backend. The local JSON engine ranks the pre-rendered
 * index in-process; the Scout engine delegates to Meilisearch, Typesense or
 * Algolia. Both speak in terms of the same index entries so the controller
 * and frontend stay backend-agnostic.
 */
interface SearchEngine
{
    /**
     * Return the index entries matching the query, most relevant first.
     *
     * @param  array<int, array{slug: string, title: string, group: string, content: string, rank: float}>  $index
     * @return array<int, array{slug: string, title: string, group: string, content: string, rank: float}>
     */
    public function search(string $query, array $index, int $limit): array;

    /**
     * Push the full index to the backend, replacing what was there before.
     * A no-op for engines that read the index directly at query time.
     *
     * @param  array<int, array{slug: string, title: string, group: string, content: string, rank: float}>  $index
     */
    public function sync(array $index): void;

    /**
     * Remove everything this package has written to the backend.
     */
    public function flush(): void;

    /**
     * The engine's short name, surfaced by the CLI and `about` command.
     */
    public function name(): string;
}

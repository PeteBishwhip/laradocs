<?php

declare(strict_types=1);

namespace Laradocs\Search;

/**
 * A lightweight, database-free stand-in for a Scout "searchable" model. It
 * exposes just the surface Scout engines touch when indexing and searching,
 * so docs loaded from the filesystem can be pushed to Meilisearch, Typesense
 * or Algolia without an Eloquent model or a migration.
 */
final class SearchableDocument
{
    public function __construct(
        private readonly string $index,
        private readonly string $slug = '',
        private readonly string $title = '',
        private readonly string $content = '',
        private readonly string $group = '',
    ) {}

    public function searchableAs(): string
    {
        return $this->index;
    }

    public function getScoutKeyName(): string
    {
        return 'slug';
    }

    public function getScoutKey(): string
    {
        return $this->slug;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'content' => $this->content,
            'group' => $this->group,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function scoutMetadata(): array
    {
        return [];
    }
}

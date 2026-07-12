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
    /**
     * @readonly
     * @var string
     */
    private $index;
    /**
     * @readonly
     * @var string
     */
    private $slug = '';
    /**
     * @readonly
     * @var string
     */
    private $title = '';
    /**
     * @readonly
     * @var string
     */
    private $content = '';
    /**
     * @readonly
     * @var string
     */
    private $group = '';
    public function __construct(string $index, string $slug = '', string $title = '', string $content = '', string $group = '')
    {
        $this->index = $index;
        $this->slug = $slug;
        $this->title = $title;
        $this->content = $content;
        $this->group = $group;
    }

    public function searchableAs(): string
    {
        return $this->index;
    }

    public function indexableAs(): string
    {
        return $this->index;
    }

    public function getScoutKeyName(): string
    {
        return 'slug';
    }

    public function getScoutKey(): string
    {
        return self::scoutKeyFor($this->slug);
    }

    /**
     * Map a docs slug to a Scout-engine-safe primary key.
     *
     * Meilisearch (and Algolia's objectID) only accept primary keys made of
     * a-z, A-Z, 0-9, hyphens and underscores, and reject the empty string.
     * Doc slugs are routed paths like "guide/routing", so we encode the path
     * separator as "__" — a sequence vanishingly unlikely to appear in a
     * real slug — and apply the same transform on the read side to map hits
     * back to entries. The root index page has an empty slug, which we map
     * to a fixed sentinel so it has a valid primary key. The sentinel can't
     * collide with a real slug: SlugResolver strips leading slashes and
     * Str::slug() never emits leading underscores, so no derived key starts
     * with "__".
     */
    public static function scoutKeyFor(string $slug): string
    {
        if ($slug === '') {
            return '__index__';
        }

        return str_replace('/', '__', $slug);
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

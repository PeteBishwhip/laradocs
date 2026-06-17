<?php

declare(strict_types=1);

namespace Laradocs\Documents;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use Laradocs\Metadata\Metadata;

/**
 * Immutable representation of a single documentation page.
 *
 * @implements Arrayable<string, mixed>
 */
final class Document implements Arrayable
{
    /**
     * @param  string|null  $locale  The content locale this page is written in,
     *                               derived from a `page.fr.md` suffix or a
     *                               `fr/page.md` directory. Null when content
     *                               localisation is not in play.
     */
    public function __construct(
        public readonly string $path,
        public readonly string $relativePath,
        public readonly string $slug,
        public readonly Metadata $metadata,
        public readonly string $markdown,
        public readonly ?string $html = null,
        public readonly int $modifiedAt = 0,
        public readonly ?string $locale = null,
    ) {}

    /**
     * Return a copy of this document with rendered HTML attached.
     */
    public function withHtml(string $html): self
    {
        return new self(
            $this->path,
            $this->relativePath,
            $this->slug,
            $this->metadata,
            $this->markdown,
            $html,
            $this->modifiedAt,
            $this->locale,
        );
    }

    /**
     * Human friendly title: explicit metadata, else derived from the filename.
     */
    public function title(): string
    {
        if ($this->metadata->title !== null && $this->metadata->title !== '') {
            return $this->metadata->title;
        }

        $basename = basename($this->relativePath);
        $name = Str::beforeLast($basename, '.');

        return Str::of($name)->replace(['-', '_'], ' ')->title()->toString();
    }

    public function isHidden(): bool
    {
        return $this->metadata->hidden;
    }

    /**
     * Whether this page should be included in the full-text search index.
     */
    public function isSearchable(): bool
    {
        return $this->metadata->searchable;
    }

    /**
     * Rank multiplier applied to this page's relevance score in the JSON engine.
     * Values > 1.0 boost the page; values < 1.0 demote it.
     */
    public function searchRank(): float
    {
        return $this->metadata->searchRank;
    }

    public function order(): int
    {
        return $this->metadata->order;
    }

    public function group(): ?string
    {
        return $this->metadata->group;
    }

    public function redirect(): ?string
    {
        return $this->metadata->redirect;
    }

    /**
     * Slug split into its path segments (e.g. "guide/intro" => ["guide","intro"]).
     *
     * @return array<int, string>
     */
    public function segments(): array
    {
        return $this->slug === '' ? [] : explode('/', $this->slug);
    }

    public function depth(): int
    {
        return count($this->segments());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title(),
            'relative_path' => $this->relativePath,
            'locale' => $this->locale,
            'metadata' => $this->metadata->toArray(),
            'html' => $this->html,
        ];
    }
}

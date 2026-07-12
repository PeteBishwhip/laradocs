<?php

declare(strict_types=1);

namespace Laradocs\Documents;

use Illuminate\Contracts\Support\Arrayable;
use Laradocs\Metadata\Metadata;
use Override;

/**
 * Immutable representation of a single documentation page.
 *
 * @implements Arrayable<string, mixed>
 *
 * @psalm-immutable
 */
final class Document implements Arrayable
{
    /**
     * @readonly
     * @var string
     */
    public $path;
    /**
     * @readonly
     * @var string
     */
    public $relativePath;
    /**
     * @readonly
     * @var string
     */
    public $slug;
    /**
     * @readonly
     * @var \Laradocs\Metadata\Metadata
     */
    public $metadata;
    /**
     * @readonly
     * @var string
     */
    public $markdown;
    /**
     * @readonly
     * @var string|null
     */
    public $html;
    /**
     * @readonly
     * @var int
     */
    public $modifiedAt = 0;
    /**
     * @var string|null
     * @readonly
     */
    public $locale;
    /**
     * @param  string|null  $locale  The content locale this page is written in,
     *                               derived from a `page.fr.md` suffix or a
     *                               `fr/page.md` directory. Null when content
     *                               localisation is not in play.
     */
    public function __construct(string $path, string $relativePath, string $slug, Metadata $metadata, string $markdown, ?string $html = null, int $modifiedAt = 0, ?string $locale = null)
    {
        $this->path = $path;
        $this->relativePath = $relativePath;
        $this->slug = $slug;
        $this->metadata = $metadata;
        $this->markdown = $markdown;
        $this->html = $html;
        $this->modifiedAt = $modifiedAt;
        $this->locale = $locale;
    }

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
        $name = pathinfo($basename, PATHINFO_FILENAME);

        return mb_convert_case(str_replace(['-', '_'], ' ', $name), MB_CASE_TITLE, 'UTF-8');
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

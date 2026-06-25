<?php

declare(strict_types=1);

namespace Laradocs\Metadata;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\CarbonImmutable;

/**
 * Typed, immutable representation of a document's front-matter.
 *
 * @implements Arrayable<string, mixed>
 */
final class Metadata implements Arrayable
{
    /**
     * @param  array<int, string>  $tags
     * @param  array<string, mixed>  $extra  Any front-matter keys without a dedicated property.
     */
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly ?string $slug = null,
        public readonly int $order = PHP_INT_MAX,
        public readonly bool $hidden = false,
        public readonly ?string $group = null,
        public readonly ?string $badge = null,
        public readonly ?string $icon = null,
        public readonly array $tags = [],
        public readonly ?string $updatedAt = null,
        public readonly ?string $author = null,
        public readonly ?string $layout = null,
        public readonly ?string $image = null,
        public readonly ?string $redirect = null,
        public readonly bool $searchable = true,
        public readonly float $searchRank = 1.0,
        public readonly ?bool $versionBanner = null,
        public readonly ?string $unchangedSince = null,
        public readonly array $extra = [],
    ) {}

    /**
     * Build metadata from a front-matter array, layering in defaults.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $defaults
     */
    public static function fromArray(array $data, array $defaults = []): self
    {
        $data = array_merge($defaults, $data);

        $known = [
            'title', 'description', 'slug', 'order', 'hidden', 'group',
            'badge', 'icon', 'tags', 'updated_at', 'author', 'layout',
            'image', 'redirect', 'search', 'search_rank',
            'version_banner', 'unchanged_since',
        ];

        $extra = array_diff_key($data, array_flip($known));

        $tags = $data['tags'] ?? [];

        return new self(
            title: self::nullableString($data, 'title'),
            description: self::nullableString($data, 'description'),
            slug: self::nullableString($data, 'slug'),
            order: isset($data['order']) && is_numeric($data['order']) ? (int) $data['order'] : PHP_INT_MAX,
            hidden: self::toBool($data['hidden'] ?? false),
            group: self::nullableString($data, 'group'),
            badge: self::nullableString($data, 'badge'),
            icon: self::nullableString($data, 'icon'),
            tags: self::normaliseTags($tags),
            updatedAt: self::nullableString($data, 'updated_at'),
            author: self::nullableString($data, 'author'),
            layout: self::nullableString($data, 'layout'),
            image: self::nullableString($data, 'image'),
            redirect: self::nullableString($data, 'redirect'),
            searchable: self::toBool($data['search'] ?? true),
            searchRank: is_numeric($data['search_rank'] ?? null) ? max(0.0, (float) $data['search_rank']) : 1.0,
            versionBanner: array_key_exists('version_banner', $data) ? self::toBool($data['version_banner']) : null,
            unchangedSince: self::nullableString($data, 'unchanged_since'),
            extra: $extra,
        );
    }

    /**
     * Parse the raw updatedAt string into a CarbonImmutable instance.
     *
     * Returns CarbonImmutable (not Carbon) so callers cannot mutate the value
     * through arithmetic methods — consistent with this class being an immutable
     * value object.
     *
     * Handles YAML 1.1's silent conversion of bare date scalars (e.g. `2026-06-21`)
     * to Unix timestamps — when Symfony YAML gives us an integer, `nullableString()`
     * stores it as a numeric string such as "1782000000", which we convert back here.
     */
    public function updatedAtCarbon(): ?CarbonImmutable
    {
        if ($this->updatedAt === null) {
            return null;
        }

        if (is_numeric($this->updatedAt)) {
            return CarbonImmutable::createFromTimestamp((int) $this->updatedAt);
        }

        try {
            return CarbonImmutable::parse($this->updatedAt);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Fetch a value by key, checking dedicated properties then extra data.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (property_exists($this, $key)) {
            return $this->{$key};
        }

        return $this->extra[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge([
            'title' => $this->title,
            'description' => $this->description,
            'slug' => $this->slug,
            'order' => $this->order,
            'hidden' => $this->hidden,
            'group' => $this->group,
            'badge' => $this->badge,
            'icon' => $this->icon,
            'tags' => $this->tags,
            'updated_at' => $this->updatedAt,
            'author' => $this->author,
            'layout' => $this->layout,
            'image' => $this->image,
            'redirect' => $this->redirect,
            'search' => $this->searchable,
            'search_rank' => $this->searchRank,
            'version_banner' => $this->versionBanner,
            'unchanged_since' => $this->unchangedSince,
        ], $this->extra);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function nullableString(array $data, string $key): ?string
    {
        return isset($data[$key]) && is_scalar($data[$key]) ? (string) $data[$key] : null;
    }

    /**
     * Coerce a front-matter value to bool, treating the strings "false", "0",
     * "no" and "off" (case-insensitive) as false rather than truthy.
     */
    private static function toBool(mixed $value): bool
    {
        if (is_string($value)) {
            return ! in_array(strtolower(trim($value)), ['', 'false', '0', 'no', 'off'], true);
        }

        return (bool) $value;
    }

    /**
     * @return array<int, string>
     */
    private static function normaliseTags(mixed $tags): array
    {
        if (! is_array($tags)) {
            return is_scalar($tags) ? [(string) $tags] : [];
        }

        return array_values(array_map(
            fn (mixed $tag): string => is_scalar($tag) ? (string) $tag : '',
            $tags
        ));
    }
}

<?php

declare(strict_types=1);

namespace Laradocs\Metadata;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use Override;

/**
 * Typed, immutable representation of a document's front-matter.
 *
 * @implements Arrayable<string, mixed>
 *
 * @psalm-immutable
 */
final class Metadata implements Arrayable
{
    /**
     * @readonly
     * @var string|null
     */
    public $title;
    /**
     * @readonly
     * @var string|null
     */
    public $description;
    /**
     * @readonly
     * @var string|null
     */
    public $slug;
    /**
     * @readonly
     * @var int
     */
    public $order = PHP_INT_MAX;
    /**
     * @readonly
     * @var bool
     */
    public $hidden = false;
    /**
     * @readonly
     * @var string|null
     */
    public $group;
    /**
     * @readonly
     * @var string|null
     */
    public $badge;
    /**
     * @readonly
     * @var string|null
     */
    public $icon;
    /**
     * @var array<int, string>
     * @readonly
     */
    public $tags = [];
    /**
     * @readonly
     * @var string|null
     */
    public $updatedAt;
    /**
     * @readonly
     * @var string|null
     */
    public $author;
    /**
     * @readonly
     * @var string|null
     */
    public $layout;
    /**
     * @readonly
     * @var string|null
     */
    public $image;
    /**
     * @readonly
     * @var string|null
     */
    public $redirect;
    /**
     * @readonly
     * @var bool
     */
    public $searchable = true;
    /**
     * @readonly
     * @var float
     */
    public $searchRank = 1.0;
    /**
     * @readonly
     * @var bool|null
     */
    public $versionBanner;
    /**
     * @readonly
     * @var string|null
     */
    public $unchangedSince;
    /**
     * @var array<string, mixed>
     * @readonly
     */
    public $extra = [];
    /**
     * @param  array<int, string>  $tags
     * @param  array<string, mixed>  $extra  Any front-matter keys without a dedicated property.
     */
    public function __construct(?string $title = null, ?string $description = null, ?string $slug = null, int $order = PHP_INT_MAX, bool $hidden = false, ?string $group = null, ?string $badge = null, ?string $icon = null, array $tags = [], ?string $updatedAt = null, ?string $author = null, ?string $layout = null, ?string $image = null, ?string $redirect = null, bool $searchable = true, float $searchRank = 1.0, ?bool $versionBanner = null, ?string $unchangedSince = null, array $extra = [])
    {
        $this->title = $title;
        $this->description = $description;
        $this->slug = $slug;
        $this->order = $order;
        $this->hidden = $hidden;
        $this->group = $group;
        $this->badge = $badge;
        $this->icon = $icon;
        $this->tags = $tags;
        $this->updatedAt = $updatedAt;
        $this->author = $author;
        $this->layout = $layout;
        $this->image = $image;
        $this->redirect = $redirect;
        $this->searchable = $searchable;
        $this->searchRank = $searchRank;
        $this->versionBanner = $versionBanner;
        $this->unchangedSince = $unchangedSince;
        $this->extra = $extra;
    }

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

        return new self(
            self::nullableString($data, 'title'),
            self::nullableString($data, 'description'),
            self::nullableString($data, 'slug'),
            isset($data['order']) && is_numeric($data['order']) ? (int) $data['order'] : PHP_INT_MAX,
            self::toBool($data['hidden'] ?? false),
            self::nullableString($data, 'group'),
            self::nullableString($data, 'badge'),
            self::nullableString($data, 'icon'),
            self::normaliseTags($data['tags'] ?? []),
            self::nullableString($data, 'updated_at'),
            self::nullableString($data, 'author'),
            self::nullableString($data, 'layout'),
            self::nullableString($data, 'image'),
            self::nullableString($data, 'redirect'),
            self::toBool($data['search'] ?? true),
            is_numeric($data['search_rank'] ?? null) ? max(0.0, (float) $data['search_rank']) : 1.0,
            array_key_exists('version_banner', $data) ? self::toBool($data['version_banner']) : null,
            self::nullableString($data, 'unchanged_since'),
            $extra,
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

        try {
            // Carbon's static factories aren't annotated as pure upstream, but
            // they only ever construct a fresh, independent instance.
            /** @psalm-suppress ImpureMethodCall */
            return is_numeric($this->updatedAt)
                ? CarbonImmutable::createFromTimestamp((int) $this->updatedAt)
                : CarbonImmutable::parse($this->updatedAt);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    /**
     * Fetch a value by key, checking dedicated properties then extra data.
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
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
     * @param mixed $value
     */
    private static function toBool($value): bool
    {
        if (is_string($value)) {
            return ! in_array(strtolower(trim($value)), ['', 'false', '0', 'no', 'off'], true);
        }

        return (bool) $value;
    }

    /**
     * @return array<int, string>
     * @param mixed $tags
     */
    private static function normaliseTags($tags): array
    {
        if (! is_array($tags)) {
            return is_scalar($tags) ? [(string) $tags] : [];
        }

        return array_values(array_map(
            function ($tag): string {
                return is_scalar($tag) ? (string) $tag : '';
            },
            $tags
        ));
    }
}

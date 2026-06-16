<?php

declare(strict_types=1);

namespace Laradocs\Support;

use Laradocs\Documents\Document;

/**
 * Single source of truth for laradocs cache keys.
 *
 * The `laradocs.cache.key_prefix` config value is read on every call so consumer
 * apps can retarget the prefix per request without rebuilding the container.
 * Every cache-key construction in the package routes through this helper.
 */
final class CacheKey
{
    public static function prefix(): string
    {
        $base = Config::string('laradocs.cache.key_prefix', 'laradocs');
        $version = Config::nullableString('laradocs._current_version');

        return $version !== null ? "{$base}:{$version}" : $base;
    }

    public static function make(string ...$segments): string
    {
        return implode(':', [self::prefix(), ...$segments]);
    }

    public static function document(Document $document): string
    {
        return self::make('doc', hash('sha256', $document->path), (string) $document->modifiedAt);
    }

    public static function tree(string $signature): string
    {
        return self::make('tree', $signature);
    }

    public static function search(string $signature): string
    {
        return self::make('search', $signature);
    }

    public static function sitemap(string $signature): string
    {
        return self::make('sitemap', $signature);
    }

    public static function feed(string $signature, string $format): string
    {
        return self::make('feed', $format, $signature);
    }

    public static function index(): string
    {
        return self::make('index');
    }
}

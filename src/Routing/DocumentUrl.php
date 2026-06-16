<?php

declare(strict_types=1);

namespace Laradocs\Routing;

use Laradocs\Support\Config;
use Laradocs\Support\Version;

/**
 * Centralised URL generation for the package's routes. Every internal link is
 * built here so two things always hold:
 *
 *  1. The configured route-name prefix (laradocs.route.name) is respected, so
 *     changing it doesn't break the package's own links.
 *  2. A page's empty slug — the docs landing page, which lives on the index
 *     route rather than the catch-all show route — never reaches
 *     route('…show', ['path' => '']), which Laravel rejects as a missing
 *     required parameter.
 */
final class DocumentUrl
{
    /**
     * The route-name prefix the DocumentRouter registers routes under, e.g.
     * "laradocs.". Includes its trailing separator.
     */
    public static function prefix(): string
    {
        return Config::string('laradocs.route.name', 'laradocs.');
    }

    /**
     * URL to a documentation page by slug. An empty slug is the docs root and
     * resolves to the index route; every other slug hits the show route.
     *
     * When multi-version docs are active the current version handle is
     * prepended to the slug automatically, e.g. "v2/getting-started".
     */
    public static function toSlug(string $slug): string
    {
        $slug = trim($slug, '/');
        $version = Version::current();

        if ($version !== null) {
            $path = $slug !== '' ? "{$version}/{$slug}" : $version;

            return route(self::prefix() . 'show', ['path' => $path]);
        }

        return $slug === ''
            ? self::index()
            : route(self::prefix() . 'show', ['path' => $slug]);
    }

    public static function index(): string
    {
        $version = Version::current();

        if ($version !== null) {
            return route(self::prefix() . 'show', ['path' => $version]);
        }

        return route(self::prefix() . 'index');
    }

    /**
     * URL to a documentation page in a specific version. Used by the version
     * switcher to cross-link to the same page in a different version.
     */
    public static function forVersion(string $slug, string $version): string
    {
        $slug = trim($slug, '/');
        $path = $slug !== '' ? "{$version}/{$slug}" : $version;

        return route(self::prefix() . 'show', ['path' => $path]);
    }

    public static function asset(string $file): string
    {
        return route(self::prefix() . 'asset', ['file' => $file]);
    }

    public static function search(): string
    {
        return route(self::prefix() . 'search');
    }

    /**
     * URL to the global tag index (e.g. /docs/tags).
     */
    public static function tags(): string
    {
        return route(self::prefix() . 'tags.index');
    }

    /**
     * URL to a single tag's listing page (e.g. /docs/tag/getting-started).
     */
    public static function tag(string $slug): string
    {
        return route(self::prefix() . 'tags.show', ['tag' => $slug]);
    }

    public static function sitemap(): string
    {
        return route(self::prefix() . 'sitemap');
    }

    public static function feed(): string
    {
        return route(self::prefix() . 'feed');
    }

    public static function apiTree(): string
    {
        return route(self::prefix() . 'api.tree');
    }

    public static function apiSearch(): string
    {
        return route(self::prefix() . 'api.search');
    }
}

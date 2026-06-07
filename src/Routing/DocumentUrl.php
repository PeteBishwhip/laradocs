<?php

declare(strict_types=1);

namespace Laradocs\Routing;

use Laradocs\Support\Config;

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
     */
    public static function toSlug(string $slug): string
    {
        $slug = trim($slug, '/');

        return $slug === ''
            ? self::index()
            : route(self::prefix() . 'show', ['path' => $slug]);
    }

    public static function index(): string
    {
        return route(self::prefix() . 'index');
    }

    public static function asset(string $file): string
    {
        return route(self::prefix() . 'asset', ['file' => $file]);
    }

    public static function search(): string
    {
        return route(self::prefix() . 'search');
    }

    public static function sitemap(): string
    {
        return route(self::prefix() . 'sitemap');
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

<?php

declare(strict_types=1);

namespace Laradocs\Routing;

use Laradocs\Seo\OgImage;
use Laradocs\Support\Config;
use Laradocs\Support\Locale;
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
     *
     * When cookie persistence is disabled (`locale.cookie = false`) and the
     * active locale differs from the default, a `?lang=` query parameter is
     * appended automatically so the language survives navigation without
     * requiring a cookie.
     */
    public static function toSlug(string $slug): string
    {
        $slug = trim($slug, '/');
        $version = Version::current();

        if ($version !== null) {
            $path = $slug !== '' ? "{$version}/{$slug}" : $version;

            return route(self::prefix() . 'show', array_merge(['path' => $path], self::langParam()));
        }

        if ($slug === '') {
            return self::index();
        }

        return route(self::prefix() . 'show', array_merge(['path' => $slug], self::langParam()));
    }

    public static function index(): string
    {
        $version = Version::current();

        if ($version !== null) {
            return route(self::prefix() . 'show', array_merge(['path' => $version], self::langParam()));
        }

        return route(self::prefix() . 'index', self::langParam());
    }

    /**
     * URL to a documentation page in a specific version. Used by the version
     * switcher to cross-link to the same page in a different version. Language
     * is intentionally not forwarded here — the switcher constructs its own
     * URLs with the explicit target locale.
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

    /**
     * Absolute URL to the generated Open Graph image for a page, or null when
     * generation is disabled / unavailable or the route isn't registered (e.g.
     * a consumer app that owns its own docs URLs but hasn't wired an og route).
     *
     * An empty slug resolves to the landing-page card; every other slug carries
     * the active version handle, mirroring {@see self::toSlug()} so the og
     * controller resolves the same document the page itself renders.
     */
    public static function ogImage(string $slug): ?string
    {
        $slug = trim($slug, '/');
        $name = self::prefix() . ($slug === '' ? 'og.index' : 'og');

        if (! OgImage::enabled() || ! app('router')->has($name)) {
            return null;
        }

        if ($slug === '') {
            return route($name);
        }

        $version = Version::current();
        $path = $version !== null ? "{$version}/{$slug}" : $slug;

        return route($name, ['path' => $path]);
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
        return route(self::prefix() . 'tags.index', self::langParam());
    }

    /**
     * URL to a single tag's listing page (e.g. /docs/tag/getting-started).
     */
    public static function tag(string $slug): string
    {
        return route(self::prefix() . 'tags.show', array_merge(['tag' => $slug], self::langParam()));
    }

    /**
     * A `['lang' => $code]` array to append to internal route parameters when
     * cookie persistence is disabled and the active locale is not the default.
     *
     * Without a cookie to carry the choice, every internal link must forward
     * `?lang=` in its query string so the visitor stays in the selected
     * language as they navigate. When cookie persistence is enabled the cookie
     * handles this and the parameter is omitted to keep URLs clean.
     *
     * @return array<string, string>
     */
    private static function langParam(): array
    {
        if (Locale::cookieEnabled()) {
            return [];
        }

        $locale = (string) app()->getLocale();
        $default = Locale::fallback();

        return $locale !== $default ? ['lang' => $locale] : [];
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

    /**
     * URL to the versions API endpoint, listing every advertised version with
     * its metadata so clients can build version-aware navigation.
     */
    public static function apiVersions(): string
    {
        return route(self::prefix() . 'api.versions');
    }

    /**
     * URL to the tree API scoped to a specific version. The handle is carried
     * as a `?version=` query parameter (the route itself has no version
     * segment) so clients can fetch a single version's navigation tree.
     */
    public static function apiVersionTree(string $v): string
    {
        return route(self::prefix() . 'api.tree', ['version' => $v]);
    }

    /**
     * URL to the search API scoped to a specific version, mirroring
     * {@see self::apiVersionTree()} — the handle rides along as `?version=`.
     */
    public static function apiVersionSearch(string $v): string
    {
        return route(self::prefix() . 'api.search', ['version' => $v]);
    }
}

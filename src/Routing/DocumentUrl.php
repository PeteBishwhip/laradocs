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
     * The active locale and version are prepended to the path automatically, in
     * the order `{locale}/{version}/{slug}` (e.g. "fr/v2/getting-started"). The
     * default locale is served unprefixed, so only non-default languages add a
     * segment. When URL locales are disabled (`locale.url = false`) the language
     * falls back to a `?lang=` query parameter instead.
     */
    public static function toSlug(string $slug): string
    {
        $slug = trim($slug, '/');

        if ($slug === '') {
            return self::index();
        }

        $prefix = self::pathPrefix();
        $path = $prefix === '' ? $slug : "{$prefix}/{$slug}";

        return route(self::prefix() . 'show', array_merge(['path' => $path], self::langQuery()));
    }

    public static function index(): string
    {
        $prefix = self::pathPrefix();

        if ($prefix === '') {
            return route(self::prefix() . 'index', self::langQuery());
        }

        return route(self::prefix() . 'show', array_merge(['path' => $prefix], self::langQuery()));
    }

    /**
     * URL to a documentation page in a specific version. Used by the version
     * switcher to cross-link to the same page in a different version. The active
     * locale is preserved so switching version keeps the visitor's language.
     */
    public static function forVersion(string $slug, string $version): string
    {
        $slug = trim($slug, '/');
        $path = self::joinPath(Locale::segment(), $version, $slug === '' ? null : $slug);

        return route(self::prefix() . 'show', ['path' => $path]);
    }

    /**
     * URL to a documentation page in a specific locale, used to build the
     * language switcher and per-locale `hreflang` alternates. The default locale
     * is emitted unprefixed; every other locale carries its segment.
     */
    public static function localized(string $slug, string $locale): string
    {
        $slug = trim($slug, '/');
        $segment = $locale === Locale::fallback() ? null : $locale;
        $path = self::joinPath($segment, Version::current(), $slug === '' ? null : $slug);

        if ($path === '') {
            return route(self::prefix() . 'index');
        }

        return route(self::prefix() . 'show', ['path' => $path]);
    }

    /**
     * The leading path segments — active locale then active version — that scope
     * every generated docs URL, joined with slashes (empty when neither applies).
     */
    private static function pathPrefix(): string
    {
        return self::joinPath(Locale::segment(), Version::current());
    }

    /**
     * Join the non-empty parts with single slashes, dropping nulls and blanks.
     */
    private static function joinPath(?string ...$parts): string
    {
        return implode('/', array_filter(
            $parts,
            static fn (?string $part): bool => $part !== null && $part !== '',
        ));
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

        $path = self::joinPath(Locale::segment(), Version::current(), $slug);

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
        return route(self::prefix() . 'tags.index', self::langQuery());
    }

    /**
     * URL to a single tag's listing page (e.g. /docs/tag/getting-started).
     */
    public static function tag(string $slug): string
    {
        return route(self::prefix() . 'tags.show', array_merge(['tag' => $slug], self::langQuery()));
    }

    /**
     * The legacy `?lang=` query parameter to forward on internal links, used
     * only when URL-path locales are disabled (`locale.url = false`).
     *
     * In that mode, and when cookie persistence is also off, every internal link
     * must carry `?lang=` so a non-default language survives navigation without a
     * cookie. When URL locales are on the segment carries the language instead,
     * and when the cookie is on it does — both leave the query string clean.
     *
     * @return array<string, string>
     */
    private static function langQuery(): array
    {
        if (Locale::urlEnabled() || Locale::cookieEnabled()) {
            return [];
        }

        $locale = (string) app()->getLocale();

        return $locale !== Locale::fallback() ? ['lang' => $locale] : [];
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

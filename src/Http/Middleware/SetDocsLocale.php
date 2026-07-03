<?php

declare(strict_types=1);

namespace Laradocs\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cookie;
use Laradocs\Routing\DocumentUrl;
use Laradocs\Support\Config;
use Laradocs\Support\Locale;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the application locale for the lifetime of a docs request so the
 * bundled views render in the visitor's chosen language.
 *
 * When URL-path locales are enabled (`locale.url`, the default) the language is
 * carried in the path — /docs/fr/guide — so each locale has a canonical,
 * crawlable, cache-friendly URL. This middleware:
 *
 *  1. 301-redirects a legacy `?lang=` query to the equivalent path form.
 *  2. 301-redirects a default-locale prefix (/docs/en/guide) to the unprefixed
 *     canonical (/docs/guide) so the default language has a single URL.
 *  3. Activates a non-default locale segment and strips it from the route's
 *     `{path}` parameter, leaving the version middleware and controller to see
 *     a clean, language-free path exactly as before.
 *
 * When URL locales are off, or no locale segment is present, the locale is
 * resolved from the query/cookie/browser chain by {@see Locale::determine()},
 * preserving the original behaviour.
 *
 * The previous locale is restored once the response has rendered so a
 * long-lived worker (Laravel Octane) never carries one request's language into
 * the next.
 */
final class SetDocsLocale
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $resolved = $this->resolveRouteLocale($request);

        if ($resolved instanceof Response) {
            // A legacy ?lang= query, or a default-locale prefix, was rewritten to
            // its canonical path form — bail out with the 301 before rendering.
            return $resolved;
        }

        $previous = app()->getLocale();
        // A route segment locale wins; otherwise fall back to the query/cookie/
        // browser chain so legacy mode and unprefixed URLs still resolve.
        $locale = $resolved ?? Locale::determine($request);
        $changed = $locale !== $previous;

        if ($changed) {
            app()->setLocale($locale);
        }

        try {
            $response = $next($request);
        } finally {
            if ($changed) {
                // The docs views have already rendered to a string by this point,
                // so restoring here keeps the request's output in the chosen
                // locale while leaving the worker's global state untouched.
                app()->setLocale($previous);
            }
        }

        $this->applyCookie($request, $response, $locale);

        return $response;
    }

    /**
     * Write, or drop, the persistence cookie on the outgoing response.
     *
     * Written only when consent is granted (`Locale::cookieEnabled()`) and the
     * visitor made an explicit choice on this request. When consent is not (or
     * no longer) granted, a cookie left over from a previous, since-withdrawn
     * consent is explicitly cleared rather than merely ignored — so a visitor
     * who revokes consent doesn't keep carrying a dead preferences cookie.
     */
    private function applyCookie(Request $request, Response $response, string $locale): void
    {
        if (Locale::cookieEnabled()) {
            if (Locale::explicitChoice($request) !== null) {
                // Remember an explicit choice for a year so navigation keeps
                // the selected language without re-appending the query
                // parameter.
                $response->headers->setCookie(cookie('laradocs_locale', $locale, 60 * 24 * 365));
            }

            return;
        }

        if ($request->hasCookie('laradocs_locale')) {
            $response->headers->setCookie(Cookie::forget('laradocs_locale'));
        }
    }

    /**
     * Resolve the locale carried by the URL.
     *
     * Returns a {@see Response} when the request must be 301-redirected to a
     * canonical path form, the locale string when a non-default segment is
     * present (after stripping it from the route), or null when there is nothing
     * to do and resolution should fall through to {@see Locale::determine()}.
     */
    private function resolveRouteLocale(Request $request): Response|string|null
    {
        if (! Locale::urlEnabled()) {
            return null;
        }

        if (($shim = $this->legacyQueryRedirect($request)) !== null) {
            return $shim;
        }

        $route = $request->route();

        return $route instanceof Route
            ? $this->resolveFromRoute($route)
            : null;
    }

    /**
     * Extract and act on the locale segment carried by an already-bound route.
     *
     * Strips the segment from the route's `{path}` parameter so downstream
     * middleware and the controller receive a clean, language-free path.
     * Returns a 301 Response when the default-locale prefix must be
     * canonicalised, the locale string when a non-default segment is found, or
     * null when no locale segment is present.
     */
    private function resolveFromRoute(Route $route): Response|string|null
    {
        [$locale, $rest] = Locale::split($this->routePath($route));

        if ($locale === null) {
            return null;
        }

        // Hand the version middleware and controller a path with the locale
        // segment removed — they already know how to handle the remainder.
        $route->setParameter('path', $rest);

        if ($locale !== Locale::fallback()) {
            return $locale;
        }

        // The default locale is served unprefixed. On a user-facing page,
        // redirect /docs/en/x to the canonical /docs/x so search engines see
        // a single URL; on other catch-all routes (e.g. the og-image
        // endpoint) just drop the redundant prefix and render in place.
        return $this->isPageRoute($route)
            ? redirect()->to($this->canonical($rest), 301)
            : null;
    }

    /**
     * Whether the route is one of the user-facing doc pages (index or show), as
     * opposed to a feed/asset/API/og endpoint that merely shares the structure.
     */
    private function isPageRoute(Route $route): bool
    {
        $prefix = Config::string('laradocs.route.name', 'laradocs.');

        return $route->getName() === $prefix . 'show' || $route->getName() === $prefix . 'index';
    }

    /**
     * 301-redirect a legacy `?lang=<code>` query to the path form of the same
     * page, preserving any version segment and other query parameters. Only the
     * doc index and show routes are shimmed so API endpoints can still scope
     * themselves with `?lang=`. Returns null when there is no valid `?lang=`.
     */
    private function legacyQueryRedirect(Request $request): ?Response
    {
        $route = $request->route();

        if (! $route instanceof Route || ! $this->isPageRoute($route)) {
            return null;
        }

        $choice = Locale::explicitChoice($request);

        if ($choice === null) {
            return null;
        }

        [, $rest] = Locale::split($this->routePath($route));
        $target = DocumentUrl::localized($rest, $choice);

        /** @var array<string, mixed> $query */
        $query = $request->query();
        unset($query['lang']);

        if ($query !== []) {
            $target .= (str_contains($target, '?') ? '&' : '?') . http_build_query($query);
        }

        $response = redirect()->to($target, 301);

        $this->applyCookie($request, $response, $choice);

        return $response;
    }

    /**
     * The string value of the route's `{path}` catch-all parameter, or an empty
     * string when the route carries none (e.g. the index route).
     */
    private function routePath(Route $route): string
    {
        $path = $route->parameter('path', '');

        return is_string($path) ? $path : '';
    }

    /**
     * The canonical, unprefixed URL for a locale-stripped path — the index route
     * for the docs root, otherwise the show route.
     */
    private function canonical(string $rest): string
    {
        $prefix = Config::string('laradocs.route.name', 'laradocs.');

        return $rest === ''
            ? route($prefix . 'index')
            : route($prefix . 'show', ['path' => $rest]);
    }
}

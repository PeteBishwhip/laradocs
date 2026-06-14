<?php

declare(strict_types=1);

namespace Laradocs\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laradocs\LaradocsServiceProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the application locale for the lifetime of a docs request so the
 * bundled views render in the visitor's chosen language. The selection is
 * resolved by {@see LaradocsServiceProvider::determineLocale()} and, when made
 * via the `?lang=` query parameter, persisted in a cookie for return visits.
 */
final class SetDocsLocale
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = LaradocsServiceProvider::determineLocale($request);

        app()->setLocale($locale);

        $response = $next($request);

        $requested = $request->query('lang');

        if (is_string($requested) && $requested !== '') {
            // Remember an explicit choice for a year so navigation keeps the
            // selected language without re-appending the query parameter.
            $response->headers->setCookie(cookie('laradocs_locale', $locale, 60 * 24 * 365));
        }

        return $response;
    }
}

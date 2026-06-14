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
 *
 * The previous locale is restored once the response has rendered so a
 * long-lived worker (Laravel Octane) never carries one request's language
 * into the next.
 */
final class SetDocsLocale
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $previous = app()->getLocale();
        $locale = LaradocsServiceProvider::determineLocale($request);

        app()->setLocale($locale);

        try {
            $response = $next($request);
        } finally {
            // The docs views have already rendered to a string by this point,
            // so restoring here keeps the request's output in the chosen
            // locale while leaving the worker's global state untouched.
            app()->setLocale($previous);
        }

        $requested = $request->query('lang');

        if (is_string($requested) && $requested !== '') {
            // Remember an explicit choice for a year so navigation keeps the
            // selected language without re-appending the query parameter.
            $response->headers->setCookie(cookie('laradocs_locale', $locale, 60 * 24 * 365));
        }

        return $response;
    }
}

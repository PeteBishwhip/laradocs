<?php

declare(strict_types=1);

namespace Laradocs\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cookie;
use Laradocs\Support\Locale;

/**
 * Lets a consent-management integration (a cookie banner's JS callback) persist
 * or drop the `laradocs_locale` cookie the moment the visitor's decision
 * changes, rather than waiting for the next full-page navigation. See the
 * "Cookie persistence" guide for integration examples.
 *
 * Intended to be called with `fetch()`:
 *
 *   fetch('/docs/_laradocs/consent?locale=fr', {credentials: 'same-origin'});
 */
final class LocaleConsentController
{
    public function __invoke(Request $request): Response
    {
        $response = new Response('', 204);

        if (! Locale::cookieEnabled()) {
            // Consent isn't (or is no longer) granted — drop any cookie left
            // over from before it was withdrawn instead of leaving it inert.
            if ($request->hasCookie('laradocs_locale')) {
                $response->headers->setCookie(Cookie::forget('laradocs_locale'));
            }

            return $response;
        }

        $locale = $request->query('locale');
        $available = Locale::available();

        if (is_string($locale) && $locale !== '' && array_key_exists($locale, $available)) {
            $response->headers->setCookie(cookie('laradocs_locale', $locale, 60 * 24 * 365));
        }

        return $response;
    }
}

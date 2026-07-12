<?php

declare(strict_types=1);

use Laradocs\Support\Locale;

/**
 * The `_laradocs/consent` endpoint lets a consent banner's JS persist (or
 * drop) the `laradocs_locale` cookie the instant the visitor's decision
 * changes, via fetch() — without waiting for the next full-page navigation.
 */
beforeEach(function () {
    config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français']);

    $this->makeDocs([
        'index.md' => "---\ntitle: Home\norder: 1\n---\n# Welcome\n",
    ]);
});

it('sets the cookie for a valid locale when consent is granted', function () {
    config()->set('laradocs.locale.cookie', true);

    $this->get('/docs/_laradocs/consent?locale=fr')
        ->assertNoContent()
        ->assertCookie('laradocs_locale', 'fr');
});

it('does not set a cookie when consent is not granted', function () {
    config()->set('laradocs.locale.cookie', false);

    $this->get('/docs/_laradocs/consent?locale=fr')
        ->assertNoContent()
        ->assertCookieMissing('laradocs_locale');
});

it('clears a stale cookie when called after consent has been withdrawn', function () {
    config()->set('laradocs.locale.cookie', true);

    Locale::setCookieResolver(function () {
        return false;
    }); // the CMP says consent was just revoked

    try {
        $this->withCookies(['laradocs_locale' => 'fr'])
            ->get('/docs/_laradocs/consent?locale=fr')
            ->assertNoContent()
            ->assertCookieExpired('laradocs_locale');
    } finally {
        Locale::setCookieResolver(null);
    }
});

it('ignores a locale that is not in the available list', function () {
    config()->set('laradocs.locale.cookie', true);

    $this->get('/docs/_laradocs/consent?locale=de')
        ->assertNoContent()
        ->assertCookieMissing('laradocs_locale');
});

it('does nothing when no locale query parameter is supplied', function () {
    config()->set('laradocs.locale.cookie', true);

    $this->get('/docs/_laradocs/consent')
        ->assertNoContent()
        ->assertCookieMissing('laradocs_locale');
});

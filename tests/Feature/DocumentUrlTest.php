<?php

declare(strict_types=1);

use Illuminate\Contracts\Routing\Registrar;
use Laradocs\Routing\DocumentRouter;
use Laradocs\Routing\DocumentUrl;

it('links a non-empty slug to the show route', function () {
    expect(DocumentUrl::toSlug('guide/install'))->toBe(url('/docs/guide/install'));
});

it('trims surrounding slashes from a slug', function () {
    expect(DocumentUrl::toSlug('/guide/install/'))->toBe(url('/docs/guide/install'));
});

it('links an empty slug to the index route', function () {
    expect(DocumentUrl::toSlug(''))->toBe(url('/docs'))
        ->and(DocumentUrl::toSlug('/'))->toBe(url('/docs'));
});

it('builds index, asset, search, sitemap and api urls', function () {
    expect(DocumentUrl::index())->toBe(url('/docs'))
        ->and(DocumentUrl::asset('laradocs.css'))->toBe(url('/docs/_laradocs/asset/laradocs.css'))
        ->and(DocumentUrl::search())->toBe(url('/docs/_laradocs/search'))
        ->and(DocumentUrl::sitemap())->toBe(url('/docs/sitemap.xml'))
        ->and(DocumentUrl::apiTree())->toBe(url('/docs/_laradocs/api/tree'))
        ->and(DocumentUrl::apiSearch())->toBe(url('/docs/_laradocs/api/search'))
        ->and(DocumentUrl::apiVersions())->toBe(url('/docs/_laradocs/api/versions'));
});

it('builds version-scoped tree and search urls carrying the version as a query parameter', function () {
    expect(DocumentUrl::apiVersionTree('v2'))->toBe(url('/docs/_laradocs/api/tree') . '?version=v2')
        ->and(DocumentUrl::apiVersionSearch('v2'))->toBe(url('/docs/_laradocs/api/search') . '?version=v2');
});

// ---------------------------------------------------------------------------
// Locale in the URL path (locale.url, the default)
// ---------------------------------------------------------------------------

it('prefixes every internal doc link with the active non-default locale segment', function () {
    config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français']);
    config()->set('laradocs.locale.default', 'en');
    app()->setLocale('fr');

    expect(DocumentUrl::toSlug('guide/intro'))->toBe(url('/docs/fr/guide/intro'))
        ->and(DocumentUrl::index())->toBe(url('/docs/fr'));
});

it('leaves the default locale unprefixed for clean canonical URLs', function () {
    config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français']);
    config()->set('laradocs.locale.default', 'en');
    app()->setLocale('en');

    expect(DocumentUrl::toSlug('guide/intro'))->toBe(url('/docs/guide/intro'))
        ->and(DocumentUrl::index())->toBe(url('/docs'));
});

it('keeps the locale segment regardless of the cookie setting', function () {
    config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français']);
    config()->set('laradocs.locale.default', 'en');
    config()->set('laradocs.locale.cookie', true);
    app()->setLocale('fr');

    // The path carries the language now, so the cookie no longer changes URLs.
    expect(DocumentUrl::toSlug('guide/intro'))->toBe(url('/docs/fr/guide/intro'))
        ->and(DocumentUrl::index())->toBe(url('/docs/fr'));
});

it('builds a localized URL for any locale, default unprefixed', function () {
    config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français']);
    config()->set('laradocs.locale.default', 'en');

    expect(DocumentUrl::localized('guide/intro', 'fr'))->toBe(url('/docs/fr/guide/intro'))
        ->and(DocumentUrl::localized('guide/intro', 'en'))->toBe(url('/docs/guide/intro'))
        ->and(DocumentUrl::localized('', 'fr'))->toBe(url('/docs/fr'))
        ->and(DocumentUrl::localized('', 'en'))->toBe(url('/docs'));
});

it('falls back to ?lang= forwarding when URL-path locales are disabled', function () {
    config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français']);
    config()->set('laradocs.locale.default', 'en');
    config()->set('laradocs.locale.url', false);
    config()->set('laradocs.locale.cookie', false);
    app()->setLocale('fr');

    expect(DocumentUrl::toSlug('guide/intro'))->toBe(url('/docs/guide/intro') . '?lang=fr')
        ->and(DocumentUrl::index())->toBe(url('/docs') . '?lang=fr')
        ->and(DocumentUrl::tags())->toBe(url('/docs/tags') . '?lang=fr');
});

it('omits the ?lang= fallback when the cookie carries the choice', function () {
    config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français']);
    config()->set('laradocs.locale.default', 'en');
    config()->set('laradocs.locale.url', false);
    config()->set('laradocs.locale.cookie', true);
    app()->setLocale('fr');

    expect(DocumentUrl::toSlug('guide/intro'))->toBe(url('/docs/guide/intro'))
        ->and(DocumentUrl::index())->toBe(url('/docs'));
});

it('respects a configured route-name prefix', function () {
    config()->set('laradocs.route.name', 'manual.');

    $router = app(Registrar::class);
    (new DocumentRouter)->register($router, [
        'prefix' => 'manual',
        'name' => 'manual.',
        'middleware' => ['web'],
    ]);

    expect(DocumentUrl::prefix())->toBe('manual.')
        ->and(DocumentUrl::index())->toBe(url('/manual'))
        ->and(DocumentUrl::toSlug('guide/install'))->toBe(url('/manual/guide/install'))
        ->and(DocumentUrl::asset('laradocs.js'))->toBe(url('/manual/_laradocs/asset/laradocs.js'))
        ->and(DocumentUrl::search())->toBe(url('/manual/_laradocs/search'));
});

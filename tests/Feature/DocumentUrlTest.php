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
// Lang forwarding when cookie persistence is disabled
// ---------------------------------------------------------------------------

it('appends ?lang= to all internal links when cookie is off and locale is not the default', function () {
    config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français']);
    config()->set('laradocs.locale.default', 'en');
    config()->set('laradocs.locale.cookie', false);
    app()->setLocale('fr');

    expect(DocumentUrl::toSlug('guide/intro'))->toBe(url('/docs/guide/intro') . '?lang=fr')
        ->and(DocumentUrl::index())->toBe(url('/docs') . '?lang=fr')
        ->and(DocumentUrl::tags())->toBe(url('/docs/tags') . '?lang=fr')
        ->and(DocumentUrl::tag('getting-started'))->toBe(url('/docs/tag/getting-started') . '?lang=fr');
});

it('omits ?lang= from links when the active locale matches the default', function () {
    config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français']);
    config()->set('laradocs.locale.default', 'en');
    config()->set('laradocs.locale.cookie', false);
    app()->setLocale('en');

    expect(DocumentUrl::toSlug('guide/intro'))->toBe(url('/docs/guide/intro'))
        ->and(DocumentUrl::index())->toBe(url('/docs'));
});

it('omits ?lang= from links when locale.cookie is enabled', function () {
    config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français']);
    config()->set('laradocs.locale.default', 'en');
    config()->set('laradocs.locale.cookie', true);
    app()->setLocale('fr');

    // Cookie will carry the language; adding ?lang= would clutter every URL.
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

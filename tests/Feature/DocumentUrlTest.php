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

it('builds index, asset and search urls', function () {
    expect(DocumentUrl::index())->toBe(url('/docs'))
        ->and(DocumentUrl::asset('laradocs.css'))->toBe(url('/docs/_laradocs/asset/laradocs.css'))
        ->and(DocumentUrl::search())->toBe(url('/docs/_laradocs/search'));
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

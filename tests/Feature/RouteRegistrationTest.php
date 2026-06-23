<?php

declare(strict_types=1);

use Illuminate\Contracts\Routing\Registrar;
use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Laradocs\Http\Middleware\EnsureDocsEnabled;
use Laradocs\Http\Middleware\SetDocsLocale;
use Laradocs\Http\Middleware\SetDocsVersion;
use Laradocs\LaradocsServiceProvider;
use Laradocs\Routing\DocumentRouter;

it('defaults laradocs.route.register to true and registers the docs routes', function () {
    expect(config('laradocs.route.register'))->toBeTrue();

    $names = collect(app(Registrar::class)->getRoutes())
        ->map(fn (Route $route): ?string => $route->getName())
        ->filter()
        ->values()
        ->all();

    expect($names)->toContain('laradocs.index', 'laradocs.show');
});

it('skips route registration when laradocs.route.register is false', function () {
    config()->set('laradocs.route.register', false);

    $router = new Router(new Dispatcher);
    app()->instance(Registrar::class, $router);

    $provider = new LaradocsServiceProvider(app());
    $method = new ReflectionMethod($provider, 'registerRoutes');
    $method->invoke($provider);

    expect($router->getRoutes()->getRoutes())->toBeEmpty();
});

it('still registers routes when laradocs.route.register is explicitly true', function () {
    config()->set('laradocs.route.register', true);

    $router = new Router(new Dispatcher);
    app()->instance(Registrar::class, $router);

    $provider = new LaradocsServiceProvider(app());
    $method = new ReflectionMethod($provider, 'registerRoutes');
    $method->invoke($provider);

    $names = collect($router->getRoutes())
        ->map(fn (Route $route): ?string => $route->getName())
        ->filter()
        ->values()
        ->all();

    expect($names)->toContain('laradocs.index', 'laradocs.show');
});

it('docs index route carries the default package middleware', function () {
    $route = collect(app(Registrar::class)->getRoutes())
        ->first(fn (Route $route): bool => $route->getName() === 'laradocs.index');

    expect($route)->not->toBeNull();

    $middleware = $route->gatherMiddleware();

    expect($middleware)
        ->toContain(EnsureDocsEnabled::class)
        ->toContain(SetDocsLocale::class)
        ->toContain(SetDocsVersion::class);
});

it('uses a custom package_middleware list when set in config', function () {
    $router = new Router(new Dispatcher);

    (new DocumentRouter)->register($router, [
        'middleware' => ['web'],
        'package_middleware' => ['App\\Http\\Middleware\\Custom'],
        'prefix' => 'docs',
        'name' => 'laradocs.',
    ]);

    $route = collect($router->getRoutes())
        ->first(fn (Route $route): bool => $route->getName() === 'laradocs.index');

    expect($route)->not->toBeNull();

    $middleware = $route->gatherMiddleware();

    expect($middleware)
        ->toContain('web')
        ->toContain('App\\Http\\Middleware\\Custom')
        ->not->toContain(EnsureDocsEnabled::class)
        ->not->toContain(SetDocsLocale::class)
        ->not->toContain(SetDocsVersion::class);
});

it('falls back to the default package middleware when package_middleware key is absent', function () {
    $router = new Router(new Dispatcher);

    (new DocumentRouter)->register($router, [
        'middleware' => ['web'],
        'prefix' => 'docs',
        'name' => 'laradocs.',
    ]);

    $route = collect($router->getRoutes())
        ->first(fn (Route $route): bool => $route->getName() === 'laradocs.index');

    expect($route)->not->toBeNull();

    $middleware = $route->gatherMiddleware();

    expect($middleware)
        ->toContain(EnsureDocsEnabled::class)
        ->toContain(SetDocsLocale::class)
        ->toContain(SetDocsVersion::class);
});

it('hooks laradocs:cache and laradocs:clear into optimize by default', function () {
    // `registerCommands` only runs in console; testbench boots with the
    // package provider so the static optimize registry already reflects
    // the default config (`route.register => true`).
    expect(ServiceProvider::$optimizeCommands)->toContain('laradocs:cache')
        ->and(ServiceProvider::$optimizeClearCommands)->toContain('laradocs:clear');
});

it('skips the optimize hooks when laradocs.route.register is false', function () {
    // The `laradocs:cache` sitemap step calls `route('laradocs.index')`,
    // which only exists when the package owns the docs URL. With
    // `route.register => false`, hooking the command into `optimize`
    // would throw RouteNotFoundException on every deploy.
    $originalOptimize = ServiceProvider::$optimizeCommands;
    $originalClear = ServiceProvider::$optimizeClearCommands;

    try {
        ServiceProvider::$optimizeCommands = [];
        ServiceProvider::$optimizeClearCommands = [];

        config()->set('laradocs.route.register', false);

        $provider = new LaradocsServiceProvider(app());
        $method = new ReflectionMethod($provider, 'registerCommands');
        $method->invoke($provider);

        expect(ServiceProvider::$optimizeCommands)->not->toContain('laradocs:cache')
            ->and(ServiceProvider::$optimizeClearCommands)->not->toContain('laradocs:clear');
    } finally {
        ServiceProvider::$optimizeCommands = $originalOptimize;
        ServiceProvider::$optimizeClearCommands = $originalClear;
    }
});

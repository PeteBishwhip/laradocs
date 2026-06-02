<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Laradocs\Facades\Laradocs as LaradocsFacade;
use Laradocs\Laradocs;

it('registers the three publish groups', function () {
    $groups = array_keys(ServiceProvider::$publishGroups);

    expect($groups)->toContain('laradocs-config', 'laradocs-views', 'laradocs-assets');
});

it('resolves the facade to the core service and proxies calls', function () {
    LaradocsFacade::share('greeting', 'hi');

    expect(app(Laradocs::class))->toBeInstanceOf(Laradocs::class)
        ->and(LaradocsFacade::variableValues())->toMatchArray(['greeting' => 'hi']);
});

it('registers the default macros', function () {
    expect(app(Laradocs::class)->macroRegistry()->names())
        ->toContain('alert', 'badge', 'button', 'embed');
});

it('exposes the laradocs view namespace', function () {
    expect(view()->exists('laradocs::layout'))->toBeTrue()
        ->and(view()->exists('laradocs::show'))->toBeTrue();
});

it('lets the application override package views', function () {
    $override = resource_path('views/vendor/laradocs/empty.blade.php');
    File::ensureDirectoryExists(dirname($override));
    File::put($override, 'OVERRIDDEN EMPTY STATE');

    try {
        config()->set('laradocs.docs.path', '/definitely/missing');
        $this->get('/docs')->assertOk()->assertSee('OVERRIDDEN EMPTY STATE');
    } finally {
        File::delete($override);
    }
});

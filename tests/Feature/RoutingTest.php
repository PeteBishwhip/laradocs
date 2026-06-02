<?php

declare(strict_types=1);
use Illuminate\Contracts\Routing\Registrar;
use Laradocs\Routing\DocumentRouter;

beforeEach(function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\norder: 1\n---\n# Welcome home\n",
        'guide/getting-started.md' => "---\ntitle: Getting Started\norder: 1\n---\n## Step one\n",
        'guide/advanced.md' => "---\ntitle: Advanced\norder: 2\n---\n## Deep dive\n",
        'secret.md' => "---\ntitle: Secret\nhidden: true\n---\nShh.\n",
        'old.md' => "---\nredirect: guide/getting-started\n---\n",
    ]);
});

it('serves the docs index', function () {
    $this->get('/docs')->assertOk()->assertSee('Welcome home');
});

it('serves a nested document by slug', function () {
    $this->get('/docs/guide/getting-started')
        ->assertOk()
        ->assertSee('Step one');
});

it('shows the active page in the sidebar', function () {
    $this->get('/docs/guide/advanced')
        ->assertOk()
        ->assertSee('is-active', false);
});

it('404s on an unknown slug', function () {
    $this->get('/docs/nope/missing')->assertNotFound();
});

it('follows metadata redirects', function () {
    $this->get('/docs/old')->assertRedirect('/docs/guide/getting-started');
});

it('serves the bundled stylesheet', function () {
    $response = $this->get('/docs/_laradocs/asset/laradocs.css')->assertOk();

    expect($response->headers->get('content-type'))->toStartWith('text/css');
});

it('honours a custom route prefix', function () {
    $router = app(Registrar::class);

    (new DocumentRouter)->register($router, [
        'prefix' => 'manual',
        'name' => 'manual.',
        'middleware' => ['web'],
    ]);

    $this->get('/manual')->assertOk()->assertSee('Welcome home');
});

it('404s every docs route when disabled', function () {
    config()->set('laradocs.enabled', false);

    $this->get('/docs')->assertNotFound();
});

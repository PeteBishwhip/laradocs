<?php

declare(strict_types=1);
use Illuminate\Contracts\Routing\Registrar;
use Laradocs\Routing\DocumentRouter;

beforeEach(function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\norder: 1\n---\n# Welcome home\n",
        'guide/intro.md' => "---\ntitle: Intro\ntags: [Guide, Beginner]\ndescription: Start here.\n---\n## Intro\n",
        'guide/advanced.md' => "---\ntitle: Advanced\ntags: [Guide]\norder: 2\n---\n## Advanced\n",
        'secret.md' => "---\ntitle: Secret\nhidden: true\ntags: [Guide, Hidden]\n---\nShh.\n",
        'untagged.md' => "---\ntitle: Untagged\n---\nNothing here.\n",
    ]);
});

it('lists every tag on the global index', function () {
    $this->get('/docs/tags')
        ->assertOk()
        ->assertSee('Guide')
        ->assertSee('Beginner');
});

it('lists the pages carrying a tag', function () {
    $this->get('/docs/tag/guide')
        ->assertOk()
        ->assertSee('Intro')
        ->assertSee('Advanced');
});

it('omits hidden pages from a tag listing', function () {
    $this->get('/docs/tag/guide')
        ->assertOk()
        ->assertDontSee('Secret');
});

it('omits tags used only by hidden pages from the global index', function () {
    $this->get('/docs/tags')
        ->assertOk()
        ->assertDontSee('Hidden');
});

it('collapses tags that share a slug into one listing', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n# Home\n",
        'one.md' => "---\ntitle: One\ntags: [API]\n---\nOne.\n",
        'two.md' => "---\ntitle: Two\ntags: [api]\n---\nTwo.\n",
    ]);

    $this->get('/docs/tag/api')
        ->assertOk()
        ->assertSee('One')
        ->assertSee('Two');
});

it('404s for a tag no visible page carries', function () {
    $this->get('/docs/tag/nonexistent')->assertNotFound();
});

it('matches tags by their slug regardless of casing', function () {
    $this->get('/docs/tag/beginner')
        ->assertOk()
        ->assertSee('Intro');
});

it('surfaces a page\'s tags on the document itself', function () {
    $this->get('/docs/guide/intro')
        ->assertOk()
        ->assertSee('/docs/tag/guide', false)
        ->assertSee('/docs/tag/beginner', false);
});

it('lets a real document win the tags index slug', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n# Home\n",
        'tags.md' => "---\ntitle: Tags Page\n---\nA real page about tags.\n",
    ]);

    $this->get('/docs/tags')
        ->assertOk()
        ->assertSee('A real page about tags.');
});

it('lets a real document win a single tag slug', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n# Home\n",
        'tag/special.md' => "---\ntitle: Special\n---\nReal tag-prefixed page.\n",
    ]);

    $this->get('/docs/tag/special')
        ->assertOk()
        ->assertSee('Real tag-prefixed page.');
});

it('does not register tag routes when disabled', function () {
    config()->set('laradocs.tags.enabled', false);

    // Re-register on a fresh prefix with the feature off.
    $router = app(Registrar::class);
    (new DocumentRouter)->register($router, [
        'prefix' => 'manual',
        'name' => 'manual.',
        'middleware' => ['web'],
    ]);

    $names = collect($router->getRoutes()->getRoutes())
        ->map(fn ($route) => $route->getName())
        ->filter()
        ->values();

    expect($names)->not->toContain('manual.tags.index')
        ->and($names)->not->toContain('manual.tags.show')
        ->and($names)->toContain('manual.show');
});

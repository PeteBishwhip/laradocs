<?php

declare(strict_types=1);

// ─── Tree endpoint ────────────────────────────────────────────────────────────

it('GET api/tree returns a versioned response with a data array', function () {
    $this->makeDocs([
        'guide/intro.md' => "---\ntitle: Intro\n---\n# Intro\n",
    ]);

    $response = $this->getJson('/docs/_laradocs/api/tree')->assertOk();

    $response->assertJsonPath('version', 1)
        ->assertJsonStructure(['version', 'data']);
});

it('tree node has title, slug, url and children keys', function () {
    $this->makeDocs([
        'guide/intro.md' => "---\ntitle: Intro\n---\n# Intro\n",
    ]);

    $this->getJson('/docs/_laradocs/api/tree')
        ->assertOk()
        ->assertJsonStructure(['data' => [['title', 'slug', 'url', 'children']]]);
});

it('tree leaf nodes carry a resolved url', function () {
    $this->makeDocs([
        'intro.md' => "---\ntitle: Intro\n---\n# Intro\n",
    ]);

    $this->getJson('/docs/_laradocs/api/tree')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'intro')
        ->assertJsonPath('data.0.url', url('/docs/intro'));
});

it('section-only nodes have a null url', function () {
    $this->makeDocs([
        'guide/intro.md' => "---\ntitle: Intro\n---\n# Intro\n",
    ]);

    $response = $this->getJson('/docs/_laradocs/api/tree')->assertOk();

    // "guide" section has no document of its own.
    expect($response->json('data.0.slug'))->toBe('guide')
        ->and($response->json('data.0.url'))->toBeNull();
});

it('section nodes with an index document carry a url', function () {
    $this->makeDocs([
        'guide/_index.md' => "---\ntitle: Guide\n---\n# Guide landing\n",
        'guide/intro.md' => "---\ntitle: Intro\n---\n# Intro\n",
    ]);

    $response = $this->getJson('/docs/_laradocs/api/tree')->assertOk();

    expect($response->json('data.0.slug'))->toBe('guide')
        ->and($response->json('data.0.url'))->toBe(url('/docs/guide'));
});

it('nested children are included in the tree', function () {
    $this->makeDocs([
        'guide/intro.md' => "---\ntitle: Intro\n---\n# Intro\n",
        'guide/advanced.md' => "---\ntitle: Advanced\n---\n# Advanced\n",
    ]);

    $this->getJson('/docs/_laradocs/api/tree')
        ->assertOk()
        ->assertJsonCount(2, 'data.0.children');
});

it('hidden pages are excluded from the tree', function () {
    $this->makeDocs([
        'visible.md' => "---\ntitle: Visible\n---\n# Visible\n",
        'secret.md' => "---\ntitle: Secret\nhidden: true\n---\nShh.\n",
    ]);

    $response = $this->getJson('/docs/_laradocs/api/tree')->assertOk();

    $slugs = array_column($response->json('data'), 'slug');

    expect($slugs)->toContain('visible')
        ->and($slugs)->not->toContain('secret');
});

it('tree 404s when docs are disabled', function () {
    config()->set('laradocs.enabled', false);
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $this->getJson('/docs/_laradocs/api/tree')->assertNotFound();
});

// ─── Search endpoint ──────────────────────────────────────────────────────────

it('GET api/search returns a versioned response with a data array', function () {
    $this->makeDocs([
        'guide/install.md' => "---\ntitle: Installation\ngroup: Guide\n---\nRun composer require.\n",
    ]);

    $response = $this->getJson('/docs/_laradocs/api/search?q=composer')->assertOk();

    $response->assertJsonPath('version', 1)
        ->assertJsonStructure(['version', 'data']);
});

it('api/search result has slug, title, url, group and excerpt', function () {
    $this->makeDocs([
        'guide/install.md' => "---\ntitle: Installation\ngroup: Guide\n---\nRun composer require.\n",
    ]);

    $this->getJson('/docs/_laradocs/api/search?q=composer')
        ->assertOk()
        ->assertJsonStructure(['data' => [['slug', 'title', 'url', 'group', 'excerpt']]]);
});

it('api/search result carries the correct field values', function () {
    $this->makeDocs([
        'guide/install.md' => "---\ntitle: Installation\ngroup: Guide\n---\nRun composer require.\n",
    ]);

    $this->getJson('/docs/_laradocs/api/search?q=composer')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'guide/install')
        ->assertJsonPath('data.0.title', 'Installation')
        ->assertJsonPath('data.0.url', url('/docs/guide/install'))
        ->assertJsonPath('data.0.group', 'Guide');
});

it('api/search links the docs root to the index route', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\nRun artisan here.\n",
    ]);

    $this->getJson('/docs/_laradocs/api/search?q=artisan')
        ->assertOk()
        ->assertJsonPath('data.0.slug', '')
        ->assertJsonPath('data.0.url', url('/docs'));
});

it('api/search returns empty data for queries shorter than min_chars', function () {
    $this->makeDocs(['a.md' => "---\ntitle: Alpha\n---\nbody\n"]);

    $this->getJson('/docs/_laradocs/api/search?q=a')
        ->assertOk()
        ->assertExactJson(['version' => 1, 'data' => []]);
});

it('api/search returns empty data for a missing query string', function () {
    $this->makeDocs(['a.md' => "---\ntitle: Alpha\n---\nbody\n"]);

    $this->getJson('/docs/_laradocs/api/search')
        ->assertOk()
        ->assertExactJson(['version' => 1, 'data' => []]);
});

it('api/search ignores a non-string query parameter', function () {
    $this->makeDocs(['a.md' => "---\ntitle: Alpha\n---\nbody\n"]);

    $this->getJson('/docs/_laradocs/api/search?q[]=x')
        ->assertOk()
        ->assertExactJson(['version' => 1, 'data' => []]);
});

it('api/search 404s when docs are disabled', function () {
    config()->set('laradocs.enabled', false);
    $this->makeDocs(['a.md' => "---\ntitle: Alpha\n---\nbody\n"]);

    $this->getJson('/docs/_laradocs/api/search?q=alpha')->assertNotFound();
});

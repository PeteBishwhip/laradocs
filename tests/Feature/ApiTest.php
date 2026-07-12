<?php

declare(strict_types=1);

// ─── Shared helpers ───────────────────────────────────────────────────────────
/**
 * @param mixed $response
 */
function apiContentType($response): string
{
    return (string) $response->headers->get('Content-Type');
}

// ─── Tree endpoint ────────────────────────────────────────────────────────────

it('tree response has Content-Type application/vnd.api+json', function () {
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $response = $this->get('/docs/_laradocs/api/tree');

    expect(apiContentType($response))->toStartWith('application/vnd.api+json');
});

it('tree response has a jsonapi member with version 1.0', function () {
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $this->getJson('/docs/_laradocs/api/tree')
        ->assertOk()
        ->assertJsonPath('jsonapi.version', '1.0');
});

it('tree response has a links.self member', function () {
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $response = $this->getJson('/docs/_laradocs/api/tree')->assertOk();

    expect($response->json('links.self'))->not->toBeNull();
});

it('tree resource objects have type, id, attributes and relationships', function () {
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $this->getJson('/docs/_laradocs/api/tree')
        ->assertOk()
        ->assertJsonStructure(['data' => [['type', 'id', 'attributes', 'relationships']]]);
});

it('tree resource type is node', function () {
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $this->getJson('/docs/_laradocs/api/tree')
        ->assertOk()
        ->assertJsonPath('data.0.type', 'node');
});

it('tree resource id matches the slug', function () {
    $this->makeDocs(['intro.md' => "---\ntitle: Intro\n---\nbody\n"]);

    $this->getJson('/docs/_laradocs/api/tree')
        ->assertOk()
        ->assertJsonPath('data.0.id', 'intro');
});

it('tree attributes contain title, slug and url', function () {
    $this->makeDocs(['intro.md' => "---\ntitle: Intro\n---\nbody\n"]);

    $this->getJson('/docs/_laradocs/api/tree')
        ->assertOk()
        ->assertJsonPath('data.0.attributes.title', 'Intro')
        ->assertJsonPath('data.0.attributes.slug', 'intro')
        ->assertJsonPath('data.0.attributes.url', url('/docs/intro'));
});

it('tree section-only nodes have a null url attribute', function () {
    $this->makeDocs(['guide/intro.md' => "---\ntitle: Intro\n---\nbody\n"]);

    $this->getJson('/docs/_laradocs/api/tree')
        ->assertOk()
        ->assertJsonPath('data.0.type', 'node')
        ->assertJsonPath('data.0.id', 'guide')
        ->assertJsonPath('data.0.attributes.url', null);
});

it('tree section nodes with an index document have a url attribute', function () {
    $this->makeDocs([
        'guide/_index.md' => "---\ntitle: Guide\n---\n# Guide\n",
        'guide/intro.md' => "---\ntitle: Intro\n---\nbody\n",
    ]);

    $this->getJson('/docs/_laradocs/api/tree')
        ->assertOk()
        ->assertJsonPath('data.0.attributes.url', url('/docs/guide'));
});

it('tree children are expressed as relationship resource linkage, not nested objects', function () {
    $this->makeDocs([
        'guide/intro.md' => "---\ntitle: Intro\n---\nbody\n",
    ]);

    $response = $this->getJson('/docs/_laradocs/api/tree')->assertOk();

    $childLinkage = $response->json('data.0.relationships.children.data.0');

    expect($childLinkage)->toHaveKeys(['type', 'id'])
        ->and($childLinkage['type'])->toBe('node')
        ->and($childLinkage['id'])->toBe('guide/intro');
});

it('tree children of root nodes appear in included', function () {
    $this->makeDocs([
        'guide/intro.md' => "---\ntitle: Intro\n---\nbody\n",
    ]);

    $response = $this->getJson('/docs/_laradocs/api/tree')->assertOk();

    $included = $response->json('included');

    expect($included)->toHaveCount(1)
        ->and($included[0]['type'])->toBe('node')
        ->and($included[0]['id'])->toBe('guide/intro')
        ->and($included[0]['attributes']['title'])->toBe('Intro');
});

it('tree included resources also carry relationships', function () {
    $this->makeDocs([
        'guide/intro.md' => "---\ntitle: Intro\n---\nbody\n",
    ]);

    $response = $this->getJson('/docs/_laradocs/api/tree')->assertOk();

    expect($response->json('included.0.relationships.children.data'))->toBe([]);
});

it('tree omits included when there are no child nodes', function () {
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $response = $this->getJson('/docs/_laradocs/api/tree')->assertOk();

    expect($response->json('included'))->toBeNull();
});

it('tree hidden pages are excluded', function () {
    $this->makeDocs([
        'visible.md' => "---\ntitle: Visible\n---\nbody\n",
        'secret.md' => "---\ntitle: Secret\nhidden: true\n---\nShh.\n",
    ]);

    $response = $this->getJson('/docs/_laradocs/api/tree')->assertOk();

    $ids = array_column($response->json('data'), 'id');

    expect($ids)->toContain('visible')
        ->and($ids)->not->toContain('secret');
});

it('tree 404s when docs are disabled', function () {
    config()->set('laradocs.enabled', false);
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $this->getJson('/docs/_laradocs/api/tree')->assertNotFound();
});

// ─── Search endpoint ──────────────────────────────────────────────────────────

it('search response has Content-Type application/vnd.api+json', function () {
    $this->makeDocs(['a.md' => "---\ntitle: Alpha\n---\nbody\n"]);

    $response = $this->get('/docs/_laradocs/api/search?q=body');

    expect(apiContentType($response))->toStartWith('application/vnd.api+json');
});

it('search response has a jsonapi member with version 1.0', function () {
    $this->makeDocs(['a.md' => "---\ntitle: Alpha\n---\nbody\n"]);

    $this->getJson('/docs/_laradocs/api/search?q=body')
        ->assertOk()
        ->assertJsonPath('jsonapi.version', '1.0');
});

it('search response has a links.self member', function () {
    $this->makeDocs(['a.md' => "---\ntitle: Alpha\n---\nbody\n"]);

    $response = $this->getJson('/docs/_laradocs/api/search?q=body')->assertOk();

    expect($response->json('links.self'))->toContain('q=body');
});

it('search resource objects have type, id and attributes', function () {
    $this->makeDocs([
        'guide/install.md' => "---\ntitle: Installation\ngroup: Guide\n---\nRun composer require.\n",
    ]);

    $this->getJson('/docs/_laradocs/api/search?q=composer')
        ->assertOk()
        ->assertJsonStructure(['data' => [['type', 'id', 'attributes']]]);
});

it('search resource type is page', function () {
    $this->makeDocs(['a.md' => "---\ntitle: Alpha\n---\nbody\n"]);

    $this->getJson('/docs/_laradocs/api/search?q=body')
        ->assertOk()
        ->assertJsonPath('data.0.type', 'page');
});

it('search resource id is the slug', function () {
    $this->makeDocs([
        'guide/install.md' => "---\ntitle: Installation\n---\nRun composer require.\n",
    ]);

    $this->getJson('/docs/_laradocs/api/search?q=composer')
        ->assertOk()
        ->assertJsonPath('data.0.id', 'guide/install');
});

it('search attributes contain title, slug, url, group and excerpt', function () {
    $this->makeDocs([
        'guide/install.md' => "---\ntitle: Installation\ngroup: Guide\n---\nRun composer require.\n",
    ]);

    $this->getJson('/docs/_laradocs/api/search?q=composer')
        ->assertOk()
        ->assertJsonPath('data.0.attributes.title', 'Installation')
        ->assertJsonPath('data.0.attributes.slug', 'guide/install')
        ->assertJsonPath('data.0.attributes.url', url('/docs/guide/install'))
        ->assertJsonPath('data.0.attributes.group', 'Guide');

    expect($this->getJson('/docs/_laradocs/api/search?q=composer')->json('data.0.attributes.excerpt'))
        ->toContain('composer');
});

it('search root page uses _root as id but preserves empty slug attribute', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\nRun artisan here.\n",
    ]);

    $this->getJson('/docs/_laradocs/api/search?q=artisan')
        ->assertOk()
        ->assertJsonPath('data.0.id', '_root')
        ->assertJsonPath('data.0.attributes.slug', '')
        ->assertJsonPath('data.0.attributes.url', url('/docs'));
});

it('search returns empty data for queries shorter than min_chars', function () {
    $this->makeDocs(['a.md' => "---\ntitle: Alpha\n---\nbody\n"]);

    $this->getJson('/docs/_laradocs/api/search?q=a')
        ->assertOk()
        ->assertExactJson(['jsonapi' => ['version' => '1.0'], 'links' => ['self' => url('/docs/_laradocs/api/search?q=a')], 'data' => []]);
});

it('search returns empty data when query is absent', function () {
    $this->makeDocs(['a.md' => "---\ntitle: Alpha\n---\nbody\n"]);

    $response = $this->getJson('/docs/_laradocs/api/search')->assertOk();

    expect($response->json('jsonapi.version'))->toBe('1.0')
        ->and($response->json('data'))->toBe([]);
});

it('search ignores a non-string query parameter', function () {
    $this->makeDocs(['a.md' => "---\ntitle: Alpha\n---\nbody\n"]);

    $response = $this->getJson('/docs/_laradocs/api/search?q[]=x')->assertOk();

    expect($response->json('data'))->toBe([]);
});

it('search 404s when docs are disabled', function () {
    config()->set('laradocs.enabled', false);
    $this->makeDocs(['a.md' => "---\ntitle: Alpha\n---\nbody\n"]);

    $this->getJson('/docs/_laradocs/api/search?q=alpha')->assertNotFound();
});

it('search excerpt is empty for a body-less page matched by title', function () {
    $this->makeDocs([
        'blank.md' => "---\ntitle: Searchword\n---\n",
    ]);

    $this->getJson('/docs/_laradocs/api/search?q=searchword')
        ->assertOk()
        ->assertJsonPath('data.0.attributes.excerpt', '');
});

it('search excerpt falls back to a leading snippet when the term is title-only', function () {
    $this->makeDocs([
        'titled.md' => "---\ntitle: Keyworded Title\n---\nThis body never mentions the term.\n",
    ]);

    $excerpt = $this->getJson('/docs/_laradocs/api/search?q=keyworded')
        ->assertOk()
        ->json('data.0.attributes.excerpt');

    expect($excerpt)->toBe('This body never mentions the term.');
});

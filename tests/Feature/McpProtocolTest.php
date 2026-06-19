<?php

declare(strict_types=1);
use Laravel\Mcp\Server;

// Integration tests for the MCP server's tools. Each test goes through the real
// HTTP endpoint and requires laravel/mcp to be installed — the whole file is
// skipped when the package is absent so CI stays green without it.
beforeEach(function () {
    if (! class_exists(Server::class)) {
        $this->markTestSkipped('laravel/mcp is not installed');
    }

    config()->set('laradocs.mcp.enabled', true);
});

// ── Server identity ─────────────────────────────────────────────────────────

it('initialize returns the laradocs server name', function () {
    $this->postJson('/docs/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize'])
        ->assertOk()
        ->assertJsonPath('result.serverInfo.name', 'laradocs');
});

it('initialize advertises a protocol version and tools capability', function () {
    $response = $this->postJson('/docs/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize'])
        ->assertOk();

    expect($response->json('result.protocolVersion'))->toBeString()
        ->and($response->json('result.capabilities'))->toHaveKey('tools')
        ->and($response->json('result.serverInfo.version'))->toBeString();
});

// ── Tool schemas ─────────────────────────────────────────────────────────────

it('tools/list returns all three tool definitions with object input schemas', function () {
    $response = $this->postJson('/docs/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'])
        ->assertOk();

    $tools = $response->json('result.tools');

    expect($tools)->toBeArray()->toHaveCount(3)
        ->and(array_column($tools, 'name'))
        ->toEqualCanonicalizing(['search_docs', 'list_pages', 'fetch_page']);

    foreach ($tools as $tool) {
        expect($tool['inputSchema']['type'])->toBe('object')
            ->and($tool['description'])->toBeString()->not->toBe('');
    }
});

it('tools/list marks query as required for search_docs and provides a default limit', function () {
    $response = $this->postJson('/docs/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'])
        ->assertOk();

    $tool = collect($response->json('result.tools'))->firstWhere('name', 'search_docs');

    expect($tool['inputSchema']['required'])->toContain('query')
        ->and($tool['inputSchema']['properties'])->toHaveKeys(['query', 'limit'])
        ->and($tool['inputSchema']['properties']['limit']['default'])->toBe(10);
});

it('tools/list marks slug as required for fetch_page', function () {
    $response = $this->postJson('/docs/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'])
        ->assertOk();

    $tool = collect($response->json('result.tools'))->firstWhere('name', 'fetch_page');

    expect($tool['inputSchema']['required'])->toContain('slug');
});

it('tools/list leaves list_pages with no required fields', function () {
    $response = $this->postJson('/docs/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'])
        ->assertOk();

    $tool = collect($response->json('result.tools'))->firstWhere('name', 'list_pages');

    // With no required fields, laravel/mcp omits the `required` key entirely.
    expect($tool['inputSchema']['required'] ?? [])->toBe([])
        ->and($tool['inputSchema']['properties'])->toHaveKey('group');
});

// ── search_docs ──────────────────────────────────────────────────────────────

it('search_docs returns matching pages with slugs, titles, groups, urls, and excerpts', function () {
    $this->makeDocs([
        'guide/install.md' => "---\ntitle: Installation\ngroup: Guide\n---\nRun composer require to add the package.\n",
    ]);

    $response = $this->postJson('/docs/mcp', [
        'jsonrpc' => '2.0',
        'id' => 5,
        'method' => 'tools/call',
        'params' => ['name' => 'search_docs', 'arguments' => ['query' => 'composer']],
    ])->assertOk();

    expect($response->json('result.isError'))->toBeFalse();

    $payload = json_decode($response->json('result.content.0.text'), true);

    expect($payload['results'])->not->toBeEmpty();

    $first = $payload['results'][0];
    expect($first['slug'])->toBe('guide/install')
        ->and($first['title'])->toBe('Installation')
        ->and($first['group'])->toBe('Guide')
        ->and($first['url'])->toBe(url('/docs/guide/install'))
        ->and($first['excerpt'])->toContain('composer');
});

it('search_docs honours the limit argument', function () {
    $this->makeDocs([
        'a.md' => "---\ntitle: Alpha\n---\nshared keyword alpha\n",
        'b.md' => "---\ntitle: Bravo\n---\nshared keyword bravo\n",
        'c.md' => "---\ntitle: Charlie\n---\nshared keyword charlie\n",
    ]);

    $response = $this->postJson('/docs/mcp', [
        'jsonrpc' => '2.0',
        'id' => 6,
        'method' => 'tools/call',
        'params' => ['name' => 'search_docs', 'arguments' => ['query' => 'shared', 'limit' => 1]],
    ])->assertOk();

    $payload = json_decode($response->json('result.content.0.text'), true);

    expect($payload['results'])->toHaveCount(1);
});

it('search_docs returns an empty results array when nothing matches', function () {
    $this->makeDocs([
        'guide/install.md' => "---\ntitle: Installation\n---\nRun composer require here.\n",
    ]);

    $response = $this->postJson('/docs/mcp', [
        'jsonrpc' => '2.0',
        'id' => 7,
        'method' => 'tools/call',
        'params' => ['name' => 'search_docs', 'arguments' => ['query' => 'zzzznomatchzzzz']],
    ])->assertOk();

    expect($response->json('result.isError'))->toBeFalse();

    $payload = json_decode($response->json('result.content.0.text'), true);

    expect($payload['results'])->toBe([]);
});

it('search_docs without a query argument returns a tool error', function () {
    $this->postJson('/docs/mcp', [
        'jsonrpc' => '2.0',
        'id' => 8,
        'method' => 'tools/call',
        'params' => ['name' => 'search_docs', 'arguments' => []],
    ])
        ->assertOk()
        ->assertJsonPath('result.isError', true);
});

// ── list_pages ───────────────────────────────────────────────────────────────

it('list_pages returns visible pages and excludes hidden ones', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n# Home\n",
        'guide/install.md' => "---\ntitle: Installation\ngroup: Guide\n---\nInstall it.\n",
        'secret.md' => "---\ntitle: Secret\nhidden: true\n---\nHidden away.\n",
    ]);

    $response = $this->postJson('/docs/mcp', [
        'jsonrpc' => '2.0',
        'id' => 10,
        'method' => 'tools/call',
        'params' => ['name' => 'list_pages'],
    ])->assertOk();

    expect($response->json('result.isError'))->toBeFalse();

    $payload = json_decode($response->json('result.content.0.text'), true);

    $slugs = array_column($payload['pages'], 'slug');
    expect($slugs)->toContain('guide/install')
        ->and($slugs)->not->toContain('secret');
});

it('list_pages returns each page with slug, title, group, and url', function () {
    $this->makeDocs([
        'guide/install.md' => "---\ntitle: Installation\ngroup: Guide\n---\nInstall it.\n",
    ]);

    $response = $this->postJson('/docs/mcp', [
        'jsonrpc' => '2.0',
        'id' => 11,
        'method' => 'tools/call',
        'params' => ['name' => 'list_pages'],
    ])->assertOk();

    $payload = json_decode($response->json('result.content.0.text'), true);

    $page = collect($payload['pages'])->firstWhere('slug', 'guide/install');

    expect($page)->toHaveKeys(['slug', 'title', 'group', 'url'])
        ->and($page['title'])->toBe('Installation')
        ->and($page['group'])->toBe('Guide')
        ->and($page['url'])->toBe(url('/docs/guide/install'));
});

it('list_pages with a group filter returns only matching pages', function () {
    $this->makeDocs([
        'guide/install.md' => "---\ntitle: Installation\ngroup: Guide\n---\nInstall it.\n",
        'api/auth.md' => "---\ntitle: Auth\ngroup: API\n---\nAuthenticate.\n",
    ]);

    $response = $this->postJson('/docs/mcp', [
        'jsonrpc' => '2.0',
        'id' => 12,
        'method' => 'tools/call',
        'params' => ['name' => 'list_pages', 'arguments' => ['group' => 'Guide']],
    ])->assertOk();

    $payload = json_decode($response->json('result.content.0.text'), true);

    $groups = array_column($payload['pages'], 'group');
    expect($groups)->not->toBeEmpty()
        ->and(array_unique($groups))->toBe(['Guide']);
});

// ── fetch_page ───────────────────────────────────────────────────────────────

it('fetch_page returns the raw markdown and full metadata for a valid slug', function () {
    $this->makeDocs([
        'guide/install.md' => "---\ntitle: Installation\ngroup: Guide\ndescription: How to install\norder: 2\n---\nRun the installer.\n",
    ]);

    $response = $this->postJson('/docs/mcp', [
        'jsonrpc' => '2.0',
        'id' => 13,
        'method' => 'tools/call',
        'params' => ['name' => 'fetch_page', 'arguments' => ['slug' => 'guide/install']],
    ])->assertOk();

    expect($response->json('result.isError'))->toBeFalse();

    $payload = json_decode($response->json('result.content.0.text'), true);

    expect($payload['slug'])->toBe('guide/install')
        ->and($payload['title'])->toBe('Installation')
        ->and($payload['group'])->toBe('Guide')
        ->and($payload['url'])->toBe(url('/docs/guide/install'))
        ->and($payload['markdown'])->toContain('Run the installer.')
        ->and($payload['metadata'])->toHaveKeys(['description', 'hidden', 'order'])
        ->and($payload['metadata']['description'])->toBe('How to install')
        ->and($payload['metadata']['hidden'])->toBeFalse()
        ->and($payload['metadata']['order'])->toBe(2);
});

it('fetch_page reports a null order when none is set', function () {
    config()->set('laradocs.metadata.default', ['hidden' => false]);

    $this->makeDocs([
        'guide/install.md' => "---\ntitle: Installation\n---\nInstall it.\n",
    ]);

    $response = $this->postJson('/docs/mcp', [
        'jsonrpc' => '2.0',
        'id' => 14,
        'method' => 'tools/call',
        'params' => ['name' => 'fetch_page', 'arguments' => ['slug' => 'guide/install']],
    ])->assertOk();

    $payload = json_decode($response->json('result.content.0.text'), true);

    expect($payload['metadata']['order'])->toBeNull();
});

it('fetch_page returns an error result for an unknown slug', function () {
    $this->makeDocs([
        'guide/install.md' => "---\ntitle: Installation\n---\nInstall it.\n",
    ]);

    $this->postJson('/docs/mcp', [
        'jsonrpc' => '2.0',
        'id' => 15,
        'method' => 'tools/call',
        'params' => ['name' => 'fetch_page', 'arguments' => ['slug' => 'nope/missing']],
    ])
        ->assertOk()
        ->assertJsonPath('result.isError', true)
        ->assertJsonPath('result.content.0.text', 'Page not found: nope/missing');
});

it('fetch_page without a slug argument returns a tool error', function () {
    $this->postJson('/docs/mcp', [
        'jsonrpc' => '2.0',
        'id' => 16,
        'method' => 'tools/call',
        'params' => ['name' => 'fetch_page', 'arguments' => []],
    ])
        ->assertOk()
        ->assertJsonPath('result.isError', true);
});

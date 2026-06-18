<?php

declare(strict_types=1);

use Illuminate\Contracts\Routing\Registrar;
use Illuminate\Routing\Route;
use Illuminate\Testing\TestResponse;

const MCP_ENDPOINT = '/docs/_laradocs/mcp';

beforeEach(function () {
    config()->set('laradocs.mcp.enabled', true);
});

/**
 * POST a raw (already-encoded) body so malformed payloads survive intact.
 */
function postRawMcp(string $body): TestResponse
{
    return test()->call(
        'POST',
        MCP_ENDPOINT,
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        $body,
    );
}

// 1. initialize
it('initialize returns protocol version, server name and tools capability', function () {
    $response = $this->postJson(MCP_ENDPOINT, [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
    ])->assertOk();

    $response->assertJsonPath('result.serverInfo.name', 'laradocs');

    expect($response->json('result.protocolVersion'))->not->toBeNull()
        ->and($response->json('result.capabilities'))->toHaveKey('tools');
});

// 2. notification (no id) => 202 empty body
it('treats a request with no id as a notification returning 202 with an empty body', function () {
    $response = $this->postJson(MCP_ENDPOINT, [
        'jsonrpc' => '2.0',
        'method' => 'ping',
    ]);

    $response->assertStatus(202);
    expect($response->getContent())->toBe('');
});

// 3. tools/list returns all three tools with object input schemas
it('tools/list returns all three tools each with an object input schema', function () {
    $response = $this->postJson(MCP_ENDPOINT, [
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/list',
    ])->assertOk();

    $tools = $response->json('result.tools');

    expect($tools)->toBeArray()->toHaveCount(3)
        ->and(array_column($tools, 'name'))
        ->toEqualCanonicalizing(['search_docs', 'list_pages', 'fetch_page']);

    foreach ($tools as $tool) {
        expect($tool['inputSchema']['type'])->toBe('object');
    }
});

// 4. search_docs with a match
it('search_docs returns matching content carrying a slug', function () {
    $this->makeDocs([
        'guide/install.md' => "---\ntitle: Installation\ngroup: Guide\n---\nRun composer require to install the package.\n",
    ]);

    $response = $this->postJson(MCP_ENDPOINT, [
        'jsonrpc' => '2.0',
        'id' => 3,
        'method' => 'tools/call',
        'params' => ['name' => 'search_docs', 'arguments' => ['query' => 'composer']],
    ])->assertOk();

    $payload = json_decode($response->json('result.content.0.text'), true);

    expect($payload['results'])->not->toBeEmpty()
        ->and($payload['results'][0]['slug'])->toBe('guide/install');
});

// 5. search_docs with no match => empty results, no isError
it('search_docs returns empty results and is not an error when nothing matches', function () {
    $this->makeDocs([
        'guide/install.md' => "---\ntitle: Installation\n---\nRun composer require here.\n",
    ]);

    $response = $this->postJson(MCP_ENDPOINT, [
        'jsonrpc' => '2.0',
        'id' => 4,
        'method' => 'tools/call',
        'params' => ['name' => 'search_docs', 'arguments' => ['query' => 'zzzznomatchzzzz']],
    ])->assertOk();

    expect($response->json('result.isError'))->toBeFalse();

    $payload = json_decode($response->json('result.content.0.text'), true);

    expect($payload['results'])->toBe([]);
});

// 6. list_pages excludes hidden pages
it('list_pages returns visible pages and excludes hidden ones', function () {
    $this->makeDocs([
        'guide/install.md' => "---\ntitle: Installation\ngroup: Guide\n---\nInstall it.\n",
        'secret.md' => "---\ntitle: Secret\nhidden: true\n---\nHidden away.\n",
    ]);

    $response = $this->postJson(MCP_ENDPOINT, [
        'jsonrpc' => '2.0',
        'id' => 5,
        'method' => 'tools/call',
        'params' => ['name' => 'list_pages'],
    ])->assertOk();

    $payload = json_decode($response->json('result.content.0.text'), true);

    $slugs = array_column($payload['pages'], 'slug');

    expect($slugs)->toContain('guide/install')
        ->and($slugs)->not->toContain('secret');
});

// 7. list_pages with a group filter
it('list_pages with a group filter returns only matching pages', function () {
    $this->makeDocs([
        'guide/install.md' => "---\ntitle: Installation\ngroup: Guide\n---\nInstall it.\n",
        'api/auth.md' => "---\ntitle: Auth\ngroup: API\n---\nAuthenticate.\n",
    ]);

    $response = $this->postJson(MCP_ENDPOINT, [
        'jsonrpc' => '2.0',
        'id' => 6,
        'method' => 'tools/call',
        'params' => ['name' => 'list_pages', 'arguments' => ['group' => 'Guide']],
    ])->assertOk();

    $payload = json_decode($response->json('result.content.0.text'), true);

    $groups = array_unique(array_column($payload['pages'], 'group'));

    expect($groups)->toBe(['Guide']);
});

// 8. fetch_page returns markdown for a valid slug
it('fetch_page returns markdown for a valid slug', function () {
    $this->makeDocs([
        'guide/install.md' => "---\ntitle: Installation\n---\nRun the installer now.\n",
    ]);

    $response = $this->postJson(MCP_ENDPOINT, [
        'jsonrpc' => '2.0',
        'id' => 7,
        'method' => 'tools/call',
        'params' => ['name' => 'fetch_page', 'arguments' => ['slug' => 'guide/install']],
    ])->assertOk();

    expect($response->json('result.isError'))->toBeFalse();

    $payload = json_decode($response->json('result.content.0.text'), true);

    expect($payload['slug'])->toBe('guide/install')
        ->and($payload['markdown'])->toContain('Run the installer now.');
});

// 9. fetch_page for an unknown slug => isError true
it('fetch_page returns an error result for an unknown slug', function () {
    $this->makeDocs([
        'guide/install.md' => "---\ntitle: Installation\n---\nInstall it.\n",
    ]);

    $this->postJson(MCP_ENDPOINT, [
        'jsonrpc' => '2.0',
        'id' => 8,
        'method' => 'tools/call',
        'params' => ['name' => 'fetch_page', 'arguments' => ['slug' => 'nope/missing']],
    ])
        ->assertOk()
        ->assertJsonPath('result.isError', true);
});

// 10. unknown method => -32601
it('returns method not found -32601 for an unknown method', function () {
    $this->postJson(MCP_ENDPOINT, [
        'jsonrpc' => '2.0',
        'id' => 9,
        'method' => 'frobnicate',
    ])
        ->assertOk()
        ->assertJsonPath('error.code', -32601);
});

// 11. malformed JSON => -32700
it('returns parse error -32700 for a malformed json body', function () {
    postRawMcp('{not valid json')
        ->assertOk()
        ->assertJsonPath('error.code', -32700);
});

// 12. mcp disabled => 404
it('returns 404 when laradocs.mcp.enabled is false', function () {
    config()->set('laradocs.mcp.enabled', false);

    $this->postJson(MCP_ENDPOINT, [
        'jsonrpc' => '2.0',
        'id' => 10,
        'method' => 'ping',
    ])->assertNotFound();
});

// 13. docs disabled => 404
it('returns 404 when laradocs.enabled is false', function () {
    config()->set('laradocs.enabled', false);

    $this->postJson(MCP_ENDPOINT, [
        'jsonrpc' => '2.0',
        'id' => 11,
        'method' => 'ping',
    ])->assertNotFound();
});

// 14. named route exists regardless of mcp.enabled
it('registers the laradocs.mcp named route regardless of the mcp.enabled flag', function () {
    config()->set('laradocs.mcp.enabled', false);

    $route = collect(app(Registrar::class)->getRoutes())
        ->first(fn (Route $route): bool => $route->getName() === 'laradocs.mcp');

    expect($route)->not->toBeNull();
});

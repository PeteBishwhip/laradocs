<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use Laradocs\Http\Controllers\McpController;

const MCP_URI = '/docs/_laradocs/mcp';

beforeEach(function () {
    config()->set('laradocs.mcp.enabled', true);
});

/**
 * POST a raw (already-encoded) body so malformed/batch payloads survive intact.
 */
function postMcpRaw(string $body): TestResponse
{
    return test()->call(
        'POST',
        MCP_URI,
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        $body,
    );
}

it('initialize returns the negotiated protocol version', function () {
    $this->postJson(MCP_URI, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize'])
        ->assertOk()
        ->assertJsonPath('result.protocolVersion', '2025-03-26');
});

it('initialize reports the laradocs server name', function () {
    $this->postJson(MCP_URI, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize'])
        ->assertOk()
        ->assertJsonPath('result.serverInfo.name', 'laradocs')
        ->assertJsonPath('id', 1);
});

it('initialize advertises a tools capability', function () {
    $response = $this->postJson(MCP_URI, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize'])
        ->assertOk();

    expect($response->json('result.capabilities'))->toHaveKey('tools');
    expect($response->json('result.serverInfo.version'))->toBeString();
});

it('ping returns an empty result object', function () {
    $response = $this->postJson(MCP_URI, ['jsonrpc' => '2.0', 'id' => 7, 'method' => 'ping'])
        ->assertOk()
        ->assertJsonPath('id', 7);

    expect($response->json('result'))->toBe([]);
});

it('a notification (no id) returns 202 with an empty body', function () {
    $response = $this->postJson(MCP_URI, ['jsonrpc' => '2.0', 'method' => 'ping']);

    $response->assertStatus(202);
    expect($response->getContent())->toBe('');
});

it('a malformed json body returns parse error -32700', function () {
    postMcpRaw('{not valid json')
        ->assertOk()
        ->assertJsonPath('error.code', -32700)
        ->assertJsonPath('id', null);
});

it('a non-object json body returns invalid request -32600', function () {
    postMcpRaw('42')
        ->assertOk()
        ->assertJsonPath('error.code', -32600);
});

it('a batch (array) body is rejected with -32600', function () {
    postMcpRaw('[{"jsonrpc":"2.0","id":1,"method":"ping"}]')
        ->assertOk()
        ->assertJsonPath('error.code', -32600)
        ->assertJsonPath('error.message', 'Batch requests not supported');
});

it('an unknown method returns method not found -32601', function () {
    $this->postJson(MCP_URI, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'frobnicate'])
        ->assertOk()
        ->assertJsonPath('error.code', -32601);
});

it('a request with a non-string method falls through to method not found', function () {
    $this->postJson(MCP_URI, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 123])
        ->assertOk()
        ->assertJsonPath('error.code', -32601);
});

it('tools/list returns all three tool definitions', function () {
    $response = $this->postJson(MCP_URI, ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'])
        ->assertOk();

    $tools = $response->json('result.tools');

    expect($tools)->toBeArray()->toHaveCount(3);

    $names = array_column($tools, 'name');
    expect($names)->toEqualCanonicalizing(['search_docs', 'list_pages', 'fetch_page']);
});

it('tools/list gives every tool an object input schema', function () {
    $response = $this->postJson(MCP_URI, ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'])
        ->assertOk();

    foreach ($response->json('result.tools') as $tool) {
        expect($tool['inputSchema']['type'])->toBe('object')
            ->and($tool['inputSchema'])->toHaveKeys(['properties', 'required'])
            ->and($tool['description'])->toBeString()->not->toBe('');
    }
});

it('tools/list marks query as required for search_docs', function () {
    $response = $this->postJson(MCP_URI, ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'])
        ->assertOk();

    $tool = collect($response->json('result.tools'))->firstWhere('name', 'search_docs');

    expect($tool['inputSchema']['required'])->toBe(['query'])
        ->and($tool['inputSchema']['properties'])->toHaveKeys(['query', 'limit'])
        ->and($tool['inputSchema']['properties']['limit']['default'])->toBe(10);
});

it('tools/list marks slug as required for fetch_page', function () {
    $response = $this->postJson(MCP_URI, ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'])
        ->assertOk();

    $tool = collect($response->json('result.tools'))->firstWhere('name', 'fetch_page');

    expect($tool['inputSchema']['required'])->toBe(['slug']);
});

it('tools/list leaves list_pages with no required fields', function () {
    $response = $this->postJson(MCP_URI, ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'])
        ->assertOk();

    $tool = collect($response->json('result.tools'))->firstWhere('name', 'list_pages');

    expect($tool['inputSchema']['required'])->toBe([])
        ->and($tool['inputSchema']['properties'])->toHaveKey('group');
});

it('tools/call for an unknown tool returns a tool error result', function () {
    $this->postJson(MCP_URI, [
        'jsonrpc' => '2.0',
        'id' => 3,
        'method' => 'tools/call',
        'params' => ['name' => 'mystery'],
    ])
        ->assertOk()
        ->assertJsonPath('result.isError', true)
        ->assertJsonPath('result.content.0.text', 'Unknown tool: mystery');
});

it('tools/call with a missing params object returns invalid params -32602', function () {
    $this->postJson(MCP_URI, ['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call'])
        ->assertOk()
        ->assertJsonPath('error.code', -32602);
});

it('tools/call with no tool name returns invalid params -32602', function () {
    $this->postJson(MCP_URI, [
        'jsonrpc' => '2.0',
        'id' => 4,
        'method' => 'tools/call',
        'params' => ['arguments' => ['query' => 'anything']],
    ])
        ->assertOk()
        ->assertJsonPath('error.code', -32602);
});

it('search_docs returns matching pages with excerpts', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n# Home\n",
        'guide/install.md' => "---\ntitle: Installation\ngroup: Guide\n---\nRun composer require to add the package to your app.\n",
    ]);

    $response = $this->postJson(MCP_URI, [
        'jsonrpc' => '2.0',
        'id' => 5,
        'method' => 'tools/call',
        'params' => ['name' => 'search_docs', 'arguments' => ['query' => 'composer']],
    ])->assertOk();

    expect($response->json('result.isError'))->toBeFalse();

    $payload = json_decode($response->json('result.content.0.text'), true);

    expect($payload['results'])->toBeArray()->not->toBeEmpty();

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

    $response = $this->postJson(MCP_URI, [
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

    $response = $this->postJson(MCP_URI, [
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
    $this->postJson(MCP_URI, [
        'jsonrpc' => '2.0',
        'id' => 8,
        'method' => 'tools/call',
        'params' => ['name' => 'search_docs', 'arguments' => []],
    ])
        ->assertOk()
        ->assertJsonPath('result.isError', true);
});

it('list_pages returns all visible pages and excludes hidden ones', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n# Home\n",
        'guide/install.md' => "---\ntitle: Installation\ngroup: Guide\n---\nInstall it.\n",
        'secret.md' => "---\ntitle: Secret\nhidden: true\n---\nHidden away.\n",
    ]);

    $response = $this->postJson(MCP_URI, [
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

it('list_pages returns each page with slug, title, group and url fields', function () {
    $this->makeDocs([
        'guide/install.md' => "---\ntitle: Installation\ngroup: Guide\n---\nInstall it.\n",
    ]);

    $response = $this->postJson(MCP_URI, [
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

    $response = $this->postJson(MCP_URI, [
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

it('fetch_page returns the raw markdown and metadata for a valid slug', function () {
    $this->makeDocs([
        'guide/install.md' => "---\ntitle: Installation\ngroup: Guide\ndescription: How to install\norder: 2\n---\nRun the installer.\n",
    ]);

    $response = $this->postJson(MCP_URI, [
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

    $response = $this->postJson(MCP_URI, [
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

    $this->postJson(MCP_URI, [
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
    $this->postJson(MCP_URI, [
        'jsonrpc' => '2.0',
        'id' => 16,
        'method' => 'tools/call',
        'params' => ['name' => 'fetch_page', 'arguments' => []],
    ])
        ->assertOk()
        ->assertJsonPath('result.isError', true);
});

it('every response carries an application/json content type', function () {
    $response = $this->postJson(MCP_URI, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping']);

    expect($response->headers->get('Content-Type'))->toStartWith('application/json');
});

it('builds a successful tool content envelope', function () {
    $controller = app(McpController::class);

    $method = new ReflectionMethod($controller, 'toolContent');
    /** @var JsonResponse $response */
    $response = $method->invoke($controller, 9, ['answer' => 42]);

    $payload = json_decode((string) $response->getContent(), true);

    expect($payload['result']['isError'])->toBeFalse()
        ->and($payload['id'])->toBe(9)
        ->and($payload['result']['content'][0]['type'])->toBe('text')
        ->and($payload['result']['content'][0]['text'])->toBe('{"answer":42}');
});

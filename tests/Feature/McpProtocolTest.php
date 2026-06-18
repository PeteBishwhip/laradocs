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

it('tools/call tolerates a missing params object', function () {
    $this->postJson(MCP_URI, ['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call'])
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

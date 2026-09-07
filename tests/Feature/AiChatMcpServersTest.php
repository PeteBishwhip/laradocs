<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laradocs\Ai\ChatRequest;
use Laradocs\Ai\ChatService;
use Laradocs\Ai\McpServers;
use Laravel\Mcp\Client;
use Laravel\Mcp\Client\Primitives\Tool;

/**
 * User-supplied MCP servers: reaching one, filtering what it advertises, and
 * surviving one that is having a bad day.
 *
 * Every server here is faked at the HTTP layer, so the JSON-RPC handshake is
 * exercised for real without a server to run.
 */
beforeEach(function (): void {
    if (! class_exists(Client::class)) {
        $this->markTestSkipped('laravel/mcp is not installed.');
    }
});

/**
 * The three responses an HTTP MCP server gives while a client connects and
 * lists its tools: the initialize result, an accepted notification, and the
 * tool listing.
 *
 * @param  array<int, array<string, mixed>>  $tools
 */
function fakeMcpServer(array $tools): void
{
    Http::fake([
        'https://mcp.test/*' => Http::sequence()
            ->push([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [
                    'protocolVersion' => '2025-06-18',
                    'capabilities' => ['tools' => []],
                    'serverInfo' => ['name' => 'billing', 'version' => '1.0.0'],
                ],
            ])
            ->push('', 202)
            ->push(['jsonrpc' => '2.0', 'id' => 2, 'result' => ['tools' => $tools]])
            ->push('', 202),
    ]);
}

/**
 * @return array<string, mixed>
 */
function fakeMcpTool(string $name): array
{
    return [
        'name' => $name,
        'description' => 'The ' . $name . ' tool',
        'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string']]],
    ];
}

it('advertises every tool a configured server offers', function (): void {
    fakeMcpServer([fakeMcpTool('lookup_invoice'), fakeMcpTool('refund')]);

    config()->set('laradocs.ai.mcp.servers', [
        'billing' => [
            'url' => 'https://mcp.test/mcp',
            'token' => 'a-secret',
            'headers' => ['X-Tenant' => 'acme', 7 => 'dropped'],
            'timeout' => 5,
        ],
    ]);

    $tools = (new McpServers)->tools();

    expect(array_map(fn (object $tool): string => $tool->name, $tools))
        ->toBe(['lookup_invoice', 'refund']);

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer a-secret')
        && $request->hasHeader('X-Tenant', 'acme'));
});

it('narrows a server to the tools named in only', function (): void {
    fakeMcpServer([fakeMcpTool('lookup_invoice'), fakeMcpTool('refund')]);

    config()->set('laradocs.ai.mcp.servers', [
        'billing' => ['url' => 'https://mcp.test/mcp', 'only' => ['refund'], 'except' => ['refund']],
    ]);

    expect(array_map(fn (object $tool): string => $tool->name, (new McpServers)->tools()))
        ->toBe(['refund']);
});

it('drops the tools named in except', function (): void {
    fakeMcpServer([fakeMcpTool('lookup_invoice'), fakeMcpTool('refund')]);

    config()->set('laradocs.ai.mcp.servers', [
        'billing' => ['url' => 'https://mcp.test/mcp', 'except' => ['refund']],
    ]);

    expect(array_map(fn (object $tool): string => $tool->name, (new McpServers)->tools()))
        ->toBe(['lookup_invoice']);
});

it('logs and skips a server that declares neither a url nor a command', function (): void {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message): bool => str_contains($message, '[billing]')
            && str_contains($message, 'neither a "url" nor a "command"'));

    config()->set('laradocs.ai.mcp.servers', ['billing' => ['token' => 'a-secret']]);

    expect((new McpServers)->tools())->toBe([]);
});

it('logs and skips a server it cannot reach', function (): void {
    Http::fake(['https://mcp.test/*' => Http::response('nope', 500)]);

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message): bool => str_contains($message, '[billing]'));

    config()->set('laradocs.ai.mcp.servers', ['billing' => ['url' => 'https://mcp.test/mcp']]);

    expect((new McpServers)->tools())->toBe([]);
});

it('still answers from the servers that did respond', function (): void {
    Http::fake([
        'https://broken.test/*' => Http::response('nope', 500),
        'https://mcp.test/*' => Http::sequence()
            ->push([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [
                    'protocolVersion' => '2025-06-18',
                    'capabilities' => ['tools' => []],
                    'serverInfo' => ['name' => 'billing', 'version' => '1.0.0'],
                ],
            ])
            ->push('', 202)
            ->push(['jsonrpc' => '2.0', 'id' => 2, 'result' => ['tools' => [fakeMcpTool('lookup_invoice')]]])
            ->push('', 202),
    ]);

    Log::shouldReceive('warning')->once();

    config()->set('laradocs.ai.mcp.servers', [
        'broken' => ['url' => 'https://broken.test/mcp'],
        'billing' => ['url' => 'https://mcp.test/mcp'],
    ]);

    expect(array_map(fn (object $tool): string => $tool->name, (new McpServers)->tools()))
        ->toBe(['lookup_invoice']);
});

it('ignores an only or except list that is not a list', function (): void {
    fakeMcpServer([fakeMcpTool('lookup_invoice'), fakeMcpTool('refund')]);

    config()->set('laradocs.ai.mcp.servers', [
        'billing' => ['url' => 'https://mcp.test/mcp', 'only' => 'refund', 'except' => 'refund'],
    ]);

    expect(array_map(fn (object $tool): string => $tool->name, (new McpServers)->tools()))
        ->toBe(['lookup_invoice', 'refund']);
});

it('ignores a server definition that is not a definition', function (): void {
    config()->set('laradocs.ai.mcp.servers', ['billing' => 'https://mcp.test/mcp']);

    expect((new McpServers)->tools())->toBe([]);
});

it('builds a local server from a command and its arguments', function (): void {
    // A stdio server is a process, and this one exits at once rather than
    // speaking the protocol, so the handshake fails and is logged like any
    // other unreachable server. The short timeout keeps the test quick;
    // listing tools for real is the HTTP path covered above.
    config()->set('laradocs.ai.mcp.servers', [
        'local' => ['command' => 'php', 'args' => ['-r', 'exit(1);', 42], 'timeout' => 0.25],
    ]);

    Log::shouldReceive('warning')->once();

    expect((new McpServers)->tools())->toBe([]);
});

it('hands a configured server\'s tools to the assistant alongside its own', function (): void {
    if (! ChatService::installed()) {
        $this->markTestSkipped('laravel/ai is not installed.');
    }

    fakeMcpServer([fakeMcpTool('lookup_invoice')]);

    config()->set('laradocs.ai.enabled', true);
    config()->set('laradocs.ai.mcp.servers', ['billing' => ['url' => 'https://mcp.test/mcp']]);
    $this->makeDocs(['_index.md' => "---\ntitle: Home\n---\n# Home\n"]);

    $tools = [...app(ChatService::class)->agent(new ChatRequest('who owes what?'))->tools()];

    expect($tools)->toHaveCount(4)
        ->and($tools[3])->toBeInstanceOf(Tool::class)
        ->and($tools[3]->name)->toBe('lookup_invoice');
});

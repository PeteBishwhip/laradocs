<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Server;

// Smoke tests that run regardless of whether laravel/mcp is installed.
// Full tool-level integration tests live in McpProtocolTest.php (skipped
// when laravel/mcp is absent); route registration tests live in McpRouteTest.php.

const MCP_ENDPOINT = '/docs/mcp';

beforeEach(function () {
    config()->set('laradocs.mcp.enabled', true);
});

it('logs critically and returns a 500 server error when laravel/mcp is not installed', function () {
    if (class_exists(Server::class)) {
        $this->markTestSkipped('laravel/mcp is installed — the missing-package path is unreachable');
    }

    Log::shouldReceive('critical')
        ->once()
        ->withArgs(function (string $message): bool {
            return strpos($message, 'laravel/mcp') !== false;
        });

    $this->postJson(MCP_ENDPOINT, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])
        ->assertStatus(500);
});

it('responds with a json content-type when mcp is enabled', function () {
    $response = $this->postJson(MCP_ENDPOINT, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping']);

    expect($response->status())->not->toBe(404)
        ->and($response->headers->get('Content-Type'))->toContain('json');
});

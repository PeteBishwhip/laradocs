<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Laradocs\Mcp\McpServerHandler;
use Laravel\Mcp\Server\Transport\HttpTransport;

// laravel/mcp changed HttpTransport's second constructor argument in v1.0:
// it was a required `string $sessionId` and became `?Closure $handler`.
// McpServerHandler picks the right shape from the constructor itself, so the
// package works on both. These tests pin that behaviour down.

beforeEach(function () {
    if (! class_exists(HttpTransport::class)) {
        $this->markTestSkipped('laravel/mcp is not installed');
    }

    config()->set('laradocs.mcp.enabled', true);
});

it('builds a transport against whichever laravel/mcp is installed', function () {
    $request = Request::create('/docs/mcp', 'POST');
    $request->headers->set('MCP-Session-Id', 'session-abc');

    $make = (new ReflectionClass(McpServerHandler::class))->getMethod('makeTransport');

    expect($make->invoke(new McpServerHandler, $request))->toBeInstanceOf(HttpTransport::class);
});

it('builds both argument shapes, whichever version is installed', function () {
    $request = Request::create('/docs/mcp', 'POST');
    $request->headers->set('MCP-Session-Id', 'session-abc');

    $handler = new McpServerHandler;
    $reflection = new ReflectionClass($handler);
    $flag = $reflection->getProperty('transportTakesSessionId');
    $build = $reflection->getMethod('transportArguments');

    $original = $flag->getValue();

    try {
        // v0.9: the session id rides along as the second argument.
        $flag->setValue(null, true);
        expect($build->invoke($handler, $request))->toBe([$request, 'session-abc']);

        // v1.0: the transport keeps the session itself.
        $flag->setValue(null, false);
        expect($build->invoke($handler, $request))->toBe([$request]);
    } finally {
        $flag->setValue(null, $original);
    }
});

it('passes the session id only when the installed transport accepts one', function () {
    $parameters = (new ReflectionClass(HttpTransport::class))->getConstructor()?->getParameters() ?? [];
    $takesSessionId = ($parameters[1] ?? null)?->getName() === 'sessionId';

    $detect = (new ReflectionClass(McpServerHandler::class))->getMethod('transportTakesSessionId');

    expect($detect->invoke(new McpServerHandler))->toBe($takesSessionId);
});

it('answers a tools/list call over HTTP', function () {
    $this->postJson('/docs/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertOk()
        ->assertJsonPath('jsonrpc', '2.0');
});

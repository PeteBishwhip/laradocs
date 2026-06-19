<?php

declare(strict_types=1);

use Illuminate\Contracts\Routing\Registrar;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Route;
use Laradocs\Http\Controllers\McpController;
use Laradocs\Http\Middleware\EnsureDocsEnabled;
use Laradocs\Http\Middleware\EnsureMcpAuthenticated;
use Laradocs\Http\Middleware\EnsureMcpEnabled;
use Laradocs\Http\Middleware\ThrottleApiRequests;

function laradocsMcpRoute(): Route
{
    $route = collect(app(Registrar::class)->getRoutes())
        ->first(fn (Route $route): bool => $route->getName() === 'laradocs.mcp');

    expect($route)->not->toBeNull();

    return $route;
}

it('registers the named mcp route as a POST to McpController', function () {
    $route = laradocsMcpRoute();

    expect($route->methods())->toContain('POST')
        ->and($route->uri())->toBe('docs/mcp')
        ->and($route->getActionName())->toBe(McpController::class);
});

it('resolves the named mcp route to the correct url', function () {
    expect(route('laradocs.mcp'))->toEndWith('/docs/mcp');
});

it('guards the mcp route with the mcp and docs middleware', function () {
    $middleware = laradocsMcpRoute()->gatherMiddleware();

    expect($middleware)
        ->toContain(EnsureMcpEnabled::class)
        ->toContain(EnsureMcpAuthenticated::class)
        ->toContain(EnsureDocsEnabled::class)
        ->toContain(ThrottleApiRequests::class);
});

it('excludes csrf middleware so non-browser clients can call it', function () {
    $excluded = laradocsMcpRoute()->excludedMiddleware();

    expect($excluded)
        ->toContain(VerifyCsrfToken::class)
        ->toContain(PreventRequestForgery::class);
});

it('404s the mcp endpoint when mcp is disabled', function () {
    config()->set('laradocs.mcp.enabled', false);

    $this->postJson('/docs/mcp')->assertNotFound();
});

it('returns json (not 404) when mcp is enabled and the endpoint is called', function () {
    config()->set('laradocs.mcp.enabled', true);

    $response = $this->postJson('/docs/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping']);

    expect($response->status())->not->toBe(404);
    expect($response->headers->get('Content-Type'))->toContain('json');
});

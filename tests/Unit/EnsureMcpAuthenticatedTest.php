<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laradocs\Http\Middleware\EnsureMcpAuthenticated;
use Symfony\Component\HttpFoundation\Response;

it('passes through when no auth guard is configured', function () {
    config()->set('laradocs.mcp.auth.guard', null);

    $response = (new EnsureMcpAuthenticated)->handle(
        Request::create('/'),
        fn (Request $request) => new Response('ok'),
    );

    expect($response->getContent())->toBe('ok');
});

it('passes through when the configured guard authenticates the request', function () {
    config()->set('laradocs.mcp.auth.guard', 'api');

    Auth::shouldReceive('guard')
        ->with('api')
        ->andReturnSelf();
    Auth::shouldReceive('check')
        ->andReturn(true);

    $response = (new EnsureMcpAuthenticated)->handle(
        Request::create('/'),
        fn (Request $request) => new Response('ok'),
    );

    expect($response->getContent())->toBe('ok')
        ->and($response->getStatusCode())->toBe(200);
});

it('returns 401 when the configured guard rejects the request', function () {
    config()->set('laradocs.mcp.auth.guard', 'api');

    Auth::shouldReceive('guard')
        ->with('api')
        ->andReturnSelf();
    Auth::shouldReceive('check')
        ->andReturn(false);

    $response = (new EnsureMcpAuthenticated)->handle(
        Request::create('/'),
        fn () => new Response('should not reach'),
    );

    expect($response->getStatusCode())->toBe(401);
});

it('returns json with an error key on 401', function () {
    config()->set('laradocs.mcp.auth.guard', 'api');

    Auth::shouldReceive('guard')
        ->with('api')
        ->andReturnSelf();
    Auth::shouldReceive('check')
        ->andReturn(false);

    $response = (new EnsureMcpAuthenticated)->handle(
        Request::create('/'),
        fn () => new Response('should not reach'),
    );

    $body = json_decode($response->getContent(), true);

    expect($body)->toHaveKey('error')
        ->and($body['error'])->toBe('Unauthenticated');
});

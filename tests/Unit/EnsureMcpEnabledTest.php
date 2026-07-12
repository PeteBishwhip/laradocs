<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Laradocs\Http\Middleware\EnsureMcpEnabled;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

it('mcp is disabled by default', function () {
    expect(config('laradocs.mcp.enabled'))->toBeFalse();
});

it('404s when mcp is disabled', function () {
    config()->set('laradocs.mcp.enabled', false);

    (new EnsureMcpEnabled)->handle(Request::create('/'), function () {
        return new Response('ok');
    });
})->throws(NotFoundHttpException::class);

it('passes the request through when mcp is enabled', function () {
    config()->set('laradocs.mcp.enabled', true);

    $response = (new EnsureMcpEnabled)->handle(
        Request::create('/'),
        function (Request $request) {
            return new Response('ok');
        },
    );

    expect($response->getContent())->toBe('ok');
});

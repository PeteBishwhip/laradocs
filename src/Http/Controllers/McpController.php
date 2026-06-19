<?php

declare(strict_types=1);

namespace Laradocs\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Laradocs\Mcp\McpServerHandler;
use Laravel\Mcp\Server;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class McpController
{
    public function __invoke(Request $request): Response|StreamedResponse
    {
        // @codeCoverageIgnoreStart
        // Defensive guard for when the MCP route is enabled but the optional
        // laravel/mcp package is missing — a server misconfiguration, not a
        // client error. The coverage suite always installs laravel/mcp, so this
        // branch is unreachable there; it is exercised by McpTest without the
        // package present. Log the actionable detail for operators and surface
        // only a generic 500 (the real reason leaks through in local).
        if (! class_exists(Server::class)) {
            Log::critical('Laradocs MCP endpoint is enabled but the laravel/mcp package is not installed. Run: composer require laravel/mcp');

            abort(500, app()->environment('local')
                ? 'laravel/mcp is not installed. Run: composer require laravel/mcp'
                : 'Server Error');
        }
        // @codeCoverageIgnoreEnd

        return app(McpServerHandler::class)->handle($request);
    }
}

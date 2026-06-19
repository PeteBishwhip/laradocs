<?php

declare(strict_types=1);

namespace Laradocs\Mcp;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Mcp\Server\Transport\HttpTransport;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bootstraps the laravel/mcp HTTP transport and hands the request to the
 * LaradocsMcpServer. Only loaded at runtime when laravel/mcp is installed.
 */
final class McpServerHandler
{
    public function handle(Request $request): Response|StreamedResponse
    {
        $transport = new HttpTransport(
            $request,
            (string) $request->header('MCP-Session-Id'),
        );

        $server = app(LaradocsMcpServer::class, ['transport' => $transport]);
        $server->start();

        return $transport->run();
    }
}

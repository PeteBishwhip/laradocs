<?php

declare(strict_types=1);

namespace Laradocs\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * MCP (Model Context Protocol) endpoint.
 *
 * Accepts JSON-RPC requests from non-browser clients. This is a minimal
 * placeholder that establishes the route target; request handling is built
 * out in a later story.
 */
final class McpController
{
    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse([
            'jsonrpc' => '2.0',
            'id' => null,
            'error' => [
                'code' => -32601,
                'message' => 'Method not found',
            ],
        ], 200);
    }
}

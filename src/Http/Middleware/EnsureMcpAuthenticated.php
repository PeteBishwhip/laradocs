<?php

declare(strict_types=1);

namespace Laradocs\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureMcpAuthenticated
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = config('laradocs.mcp.auth.guard');

        if ($guard === null) {
            return $next($request);
        }

        if (! Auth::guard($guard)->check()) {
            return new JsonResponse(
                ['error' => 'Unauthenticated', 'message' => 'A valid authentication token is required to access the MCP endpoint.'],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        return $next($request);
    }
}

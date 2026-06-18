<?php

declare(strict_types=1);

namespace Laradocs\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureMcpEnabled
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('laradocs.mcp.enabled', false)) {
            abort(404);
        }

        return $next($request);
    }
}

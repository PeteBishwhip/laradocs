<?php

declare(strict_types=1);

namespace Laradocs\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laradocs\Ai\ChatService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the chat endpoint invisible unless the assistant is both switched on
 * and installed.
 *
 * The route is always registered so `route:cache` captures it, exactly as the
 * docs routes are; this is what actually enforces the switch. A 404 rather
 * than a 403 because a disabled feature should leave nothing to probe.
 */
final class EnsureAiEnabled
{
    public function __construct(private readonly ChatService $chat) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->chat->available()) {
            abort(404);
        }

        return $next($request);
    }
}

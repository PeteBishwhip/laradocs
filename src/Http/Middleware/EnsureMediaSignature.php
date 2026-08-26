<?php

declare(strict_types=1);

namespace Laradocs\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laradocs\Media\MediaSource;
use Laradocs\Media\MediaUrl;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces `media.signed` on the media route.
 *
 * The check lives here rather than in the route definition so the setting is
 * read per request: a route definition is evaluated once, and `route:cache`
 * would freeze whichever value happened to be set when the cache was built.
 */
final class EnsureMediaSignature
{
    public function __construct(
        private readonly MediaSource $source,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // With no media source configured the route serves nothing at all, and
        // a 404 says that more honestly than a 403 about a missing signature.
        if (! $this->source->enabled()) {
            return $next($request);
        }

        if (MediaUrl::signed() && ! $request->hasValidSignature()) {
            abort(403);
        }

        return $next($request);
    }
}

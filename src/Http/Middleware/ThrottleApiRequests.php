<?php

declare(strict_types=1);

namespace Laradocs\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laradocs\Support\RateLimiterConfig;
use Symfony\Component\HttpFoundation\Response;

final class ThrottleApiRequests
{
    public function __construct(
        private readonly RateLimiterConfig $config,
        private readonly ThrottleRequests $throttle,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->config->isDisabled()) {
            return $next($request);
        }

        return $this->throttle->handle($request, $next, 'laradocs-api');
    }
}

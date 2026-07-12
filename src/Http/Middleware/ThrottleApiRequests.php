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
    /**
     * @readonly
     * @var \Laradocs\Support\RateLimiterConfig
     */
    private $config;
    /**
     * @readonly
     * @var \Illuminate\Routing\Middleware\ThrottleRequests
     */
    private $throttle;
    public function __construct(RateLimiterConfig $config, ThrottleRequests $throttle)
    {
        $this->config = $config;
        $this->throttle = $throttle;
    }

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

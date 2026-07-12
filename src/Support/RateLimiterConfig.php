<?php

declare(strict_types=1);

namespace Laradocs\Support;

use Closure;

/**
 * Holds the user-configured API rate-limit resolver. Registered as a
 * singleton so changes made via the Laradocs facade are visible to the
 * ThrottleApiRequests middleware on every subsequent request.
 */
final class RateLimiterConfig
{
    /**
     * @var \Closure|int|false|null
     */
    private $resolver = null;

    /**
     * @param \Closure|int|false $resolver
     */
    public function set($resolver): void
    {
        $this->resolver = $resolver;
    }

    /**
     * @return \Closure|int|false|null
     */
    public function get()
    {
        return $this->resolver;
    }

    public function isDisabled(): bool
    {
        return $this->resolver === false;
    }
}

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
    private Closure|int|false|null $resolver = null;

    public function set(Closure|int|false $resolver): void
    {
        $this->resolver = $resolver;
    }

    public function get(): Closure|int|false|null
    {
        return $this->resolver;
    }

    public function isDisabled(): bool
    {
        return $this->resolver === false;
    }
}

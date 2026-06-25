<?php

declare(strict_types=1);

namespace Laradocs\Support;

use Closure;

/**
 * Holds a user-supplied "last updated" date resolver. Registered as a
 * singleton so changes made via the Laradocs facade are visible to the
 * view layer on every subsequent request.
 */
final class LastUpdatedConfig
{
    private ?Closure $resolver = null;

    public function set(?Closure $resolver): void
    {
        $this->resolver = $resolver;
    }

    public function get(): ?Closure
    {
        return $this->resolver;
    }
}

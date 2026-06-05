<?php

declare(strict_types=1);

namespace Laradocs\Search;

use Closure;
use Laradocs\Search\Contracts\SearchEngine;

/**
 * Resolves the active search engine from configuration:
 *
 *   driver = json   always use the built-in JSON index
 *   driver = scout  use Scout when it's installed, otherwise JSON
 *   driver = auto   use Scout when it's installed *and* configured, else JSON
 *
 * The Scout engine is built lazily through a closure so its classes are never
 * loaded on hosts that don't have laravel/scout installed.
 */
final class SearchManager
{
    private ?SearchEngine $resolved = null;

    /**
     * @param  Closure(): SearchEngine  $scoutFactory
     */
    public function __construct(
        private readonly string $driver,
        private readonly bool $scoutAvailable,
        private readonly bool $scoutConfigured,
        private readonly Closure $scoutFactory,
        private readonly SearchEngine $json,
    ) {}

    public function engine(): SearchEngine
    {
        return $this->resolved ??= $this->resolve();
    }

    private function resolve(): SearchEngine
    {
        return match ($this->driver) {
            'json' => $this->json,
            'scout' => $this->scoutAvailable ? ($this->scoutFactory)() : $this->json,
            default => $this->scoutAvailable && $this->scoutConfigured
                ? ($this->scoutFactory)()
                : $this->json,
        };
    }
}

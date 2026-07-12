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
    /**
     * @readonly
     * @var string
     */
    private $driver;
    /**
     * @readonly
     * @var bool
     */
    private $scoutAvailable;
    /**
     * @readonly
     * @var bool
     */
    private $scoutConfigured;
    /**
     * @var Closure():SearchEngine
     * @readonly
     */
    private $scoutFactory;
    /**
     * @readonly
     * @var \Laradocs\Search\Contracts\SearchEngine
     */
    private $json;
    /**
     * @var \Laradocs\Search\Contracts\SearchEngine|null
     */
    private $resolved;

    /**
     * @param  Closure(): SearchEngine  $scoutFactory
     */
    public function __construct(string $driver, bool $scoutAvailable, bool $scoutConfigured, Closure $scoutFactory, SearchEngine $json)
    {
        $this->driver = $driver;
        $this->scoutAvailable = $scoutAvailable;
        $this->scoutConfigured = $scoutConfigured;
        $this->scoutFactory = $scoutFactory;
        $this->json = $json;
    }

    public function engine(): SearchEngine
    {
        return $this->resolved = $this->resolved ?? $this->resolve();
    }

    private function resolve(): SearchEngine
    {
        switch ($this->driver) {
            case 'json':
                return $this->json;
            case 'scout':
                return $this->scoutAvailable ? ($this->scoutFactory)() : $this->json;
            default:
                return $this->scoutAvailable && $this->scoutConfigured
                    ? ($this->scoutFactory)()
                    : $this->json;
        }
    }
}

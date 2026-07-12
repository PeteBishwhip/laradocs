<?php

declare(strict_types=1);

namespace Laradocs\OpenApi\Generator;

/**
 * The spec-level inputs shared by every generator driver, bundled so the
 * factory and the drivers exchange one value instead of a long argument list.
 *
 * `prefix` and `middleware` filter which routes are documented (see
 * {@see RouteCollector}); the rest describe the emitted document itself.
 */
final class GeneratorOptions
{
    /**
     * @readonly
     * @var string
     */
    public $title = 'API';
    /**
     * @readonly
     * @var string
     */
    public $version = '1.0.0';
    /**
     * @readonly
     * @var string|null
     */
    public $serverUrl;
    /**
     * @readonly
     * @var string|null
     */
    public $description;
    /**
     * @var array<int|string, mixed>
     * @readonly
     */
    public $security = [];
    /**
     * @readonly
     * @var string|null
     */
    public $prefix = 'api';
    /**
     * @readonly
     * @var string|null
     */
    public $middleware = 'api';
    /**
     * @param  array<int|string, mixed>  $security
     */
    public function __construct(string $title = 'API', string $version = '1.0.0', ?string $serverUrl = null, ?string $description = null, array $security = [], ?string $prefix = 'api', ?string $middleware = 'api')
    {
        $this->title = $title;
        $this->version = $version;
        $this->serverUrl = $serverUrl;
        $this->description = $description;
        $this->security = $security;
        $this->prefix = $prefix;
        $this->middleware = $middleware;
    }
}

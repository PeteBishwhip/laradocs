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
     * @param  array<int|string, mixed>  $security
     */
    public function __construct(
        public readonly string $title = 'API',
        public readonly string $version = '1.0.0',
        public readonly ?string $serverUrl = null,
        public readonly ?string $description = null,
        public readonly array $security = [],
        public readonly ?string $prefix = 'api',
        public readonly ?string $middleware = 'api',
    ) {}
}

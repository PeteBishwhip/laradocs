<?php

declare(strict_types=1);

namespace Laradocs\OpenApi\Generator;

use Illuminate\Routing\Router;
use Laradocs\Contracts\OpenApiSpecGenerator;
use Laradocs\OpenApi\OpenApiException;

/**
 * Resolves the OpenAPI generator backend chosen by configuration or the
 * `--driver` command option into a concrete {@see OpenApiSpecGenerator}:
 *
 *   native   — always the built-in {@see SpecGenerator}
 *   scramble — the {@see ScrambleSpecGenerator} adapter, which requires
 *              dedoc/scramble; throws {@see OpenApiException} when it is missing
 *   auto     — Scramble when it is installed, otherwise the native generator
 *
 * Scramble is an optional dependency, so its availability is probed through the
 * {@see scrambleAvailable()} seam rather than a hard reference. That method is
 * `protected` so tests can subclass the factory and force either branch without
 * having to install (or uninstall) the package. The class is intentionally left
 * non-`final` to keep that seam open.
 */
class SpecGeneratorFactory
{
    public const MISSING_MESSAGE = 'The dedoc/scramble package is required for --driver=scramble. Install it with: composer require dedoc/scramble';

    public function __construct(
        private readonly Router $router,
    ) {}

    /**
     * @param  array<int|string, mixed>  $security
     */
    public function make(
        string $driver,
        string $title,
        string $version,
        ?string $serverUrl,
        ?string $description,
        array $security,
        ?string $prefix,
        ?string $middleware,
    ): OpenApiSpecGenerator {
        return match ($driver) {
            'scramble' => $this->scrambleAvailable()
                ? $this->scramble($title, $version, $serverUrl, $description, $security, $prefix, $middleware)
                : throw new OpenApiException(self::MISSING_MESSAGE),
            'auto' => $this->scrambleAvailable()
                ? $this->scramble($title, $version, $serverUrl, $description, $security, $prefix, $middleware)
                : $this->native($title, $version, $serverUrl, $prefix, $middleware),
            // 'native' and any unrecognised driver fall back to the built-in generator.
            default => $this->native($title, $version, $serverUrl, $prefix, $middleware),
        };
    }

    /**
     * Whether the optional dedoc/scramble package is installed.
     *
     * `protected` so tests can subclass this factory and override the probe
     * instead of mocking, exercising both the native and Scramble branches.
     */
    protected function scrambleAvailable(): bool
    {
        return class_exists('\Dedoc\Scramble\Generator');
    }

    private function native(
        string $title,
        string $version,
        ?string $serverUrl,
        ?string $prefix,
        ?string $middleware,
    ): SpecGenerator {
        return new SpecGenerator(
            routes: new RouteCollector($this->router, $prefix, $middleware),
            requests: new RequestInspector,
            responses: new ResponseInspector,
            title: $title,
            version: $version,
            serverUrl: $serverUrl,
        );
    }

    /**
     * @param  array<int|string, mixed>  $security
     */
    private function scramble(
        string $title,
        string $version,
        ?string $serverUrl,
        ?string $description,
        array $security,
        ?string $prefix,
        ?string $middleware,
    ): ScrambleSpecGenerator {
        return new ScrambleSpecGenerator(
            router: $this->router,
            title: $title,
            version: $version,
            serverUrl: $serverUrl,
            description: $description,
            security: $security,
            prefix: $prefix,
            middleware: $middleware,
        );
    }
}

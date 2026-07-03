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

    public function make(string $driver, GeneratorOptions $options): OpenApiSpecGenerator
    {
        return match ($driver) {
            'scramble' => $this->scrambleAvailable()
                ? $this->scramble($options)
                : throw new OpenApiException(self::MISSING_MESSAGE),
            'auto' => $this->scrambleAvailable()
                ? $this->scramble($options)
                : $this->native($options),
            // 'native' and any unrecognised driver fall back to the built-in generator.
            default => $this->native($options),
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

    private function native(GeneratorOptions $options): SpecGenerator
    {
        return new SpecGenerator(
            routes: new RouteCollector($this->router, $options->prefix, $options->middleware),
            requests: new RequestInspector,
            responses: new ResponseInspector,
            title: $options->title,
            version: $options->version,
            serverUrl: $options->serverUrl,
        );
    }

    private function scramble(GeneratorOptions $options): ScrambleSpecGenerator
    {
        return new ScrambleSpecGenerator($this->router, $options);
    }
}

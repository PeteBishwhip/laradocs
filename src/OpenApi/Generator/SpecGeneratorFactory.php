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
    /**
     * @readonly
     * @var \Illuminate\Routing\Router
     */
    private $router;
    public const MISSING_MESSAGE = 'The dedoc/scramble package is required for --driver=scramble. Install it with: composer require dedoc/scramble';

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    public function make(string $driver, GeneratorOptions $options): OpenApiSpecGenerator
    {
        switch ($driver) {
            case 'scramble':
                if (!$this->scrambleAvailable()) {
                    throw new OpenApiException(self::MISSING_MESSAGE);
                }
                return $this->scramble($options);
            case 'auto':
                return $this->scrambleAvailable()
                    ? $this->scramble($options)
                    : $this->native($options);
            default:
                return $this->native($options);
        }
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
            new RouteCollector($this->router, $options->prefix, $options->middleware),
            new RequestInspector,
            new ResponseInspector,
            $options->title,
            $options->version,
            $options->serverUrl,
        );
    }

    private function scramble(GeneratorOptions $options): ScrambleSpecGenerator
    {
        return new ScrambleSpecGenerator($this->router, $options);
    }
}

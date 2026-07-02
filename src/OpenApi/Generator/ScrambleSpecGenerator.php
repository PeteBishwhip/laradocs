<?php

declare(strict_types=1);

namespace Laradocs\OpenApi\Generator;

use Laradocs\Contracts\OpenApiSpecGenerator;

/**
 * Adapts dedoc/scramble's generator to the {@see OpenApiSpecGenerator} contract
 * so it can stand in for the built-in {@see SpecGenerator} behind
 * {@see SpecGeneratorFactory}.
 *
 * Scramble is an optional dependency: its `Dedoc\Scramble\Generator` is resolved
 * from the container at call time by class name rather than imported, so this
 * file loads cleanly on hosts that don't have the package. The factory only ever
 * constructs this adapter once {@see SpecGeneratorFactory::scrambleAvailable()}
 * has confirmed the package is installed.
 */
final class ScrambleSpecGenerator implements OpenApiSpecGenerator
{
    /**
     * Container binding for Scramble's invokable document generator.
     */
    private const GENERATOR = 'Dedoc\Scramble\Generator';

    /**
     * @param  array<int|string, mixed>  $security
     */
    public function __construct(
        private readonly string $title = 'API',
        private readonly string $version = '1.0.0',
        private readonly ?string $serverUrl = null,
        private readonly ?string $description = null,
        private readonly array $security = [],
        private readonly ?string $prefix = 'api',
        private readonly ?string $middleware = 'api',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generate(): array
    {
        $class = self::GENERATOR;

        /** @var callable(array<string, mixed>): array<string, mixed> $generator */
        $generator = app($class);

        return $this->applyOverrides($generator($this->config()));
    }

    /**
     * Translate the resolved config into the shape Scramble's generator accepts.
     *
     * @return array<string, mixed>
     */
    private function config(): array
    {
        $info = ['title' => $this->title, 'version' => $this->version];

        if ($this->description !== null && $this->description !== '') {
            $info['description'] = $this->description;
        }

        $config = ['info' => $info];

        if ($this->prefix !== null && $this->prefix !== '') {
            $config['api_path'] = trim($this->prefix, '/');
        }

        if ($this->middleware !== null && $this->middleware !== '') {
            $config['middleware'] = [$this->middleware];
        }

        if ($this->serverUrl !== null && $this->serverUrl !== '') {
            $config['servers'] = [['url' => rtrim($this->serverUrl, '/')]];
        }

        return $config;
    }

    /**
     * Overlay document-level settings Scramble's config does not cover directly.
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private function applyOverrides(array $document): array
    {
        if ($this->security !== []) {
            $document['security'] = $this->security;
        }

        return $document;
    }
}

<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use Laradocs\Contracts\OpenApiSpecGenerator;
use Laradocs\OpenApi\Generator\ScrambleSpecGenerator;
use Laradocs\OpenApi\Generator\SpecGenerator;
use Laradocs\OpenApi\Generator\SpecGeneratorFactory;
use Laradocs\OpenApi\OpenApiException;

/**
 * A factory whose Scramble probe is pinned, so both branches can be exercised
 * without installing (or uninstalling) the optional package.
 */
function fakeFactory(bool $scrambleAvailable): SpecGeneratorFactory
{
    /** @var Router $router */
    $router = app('router');

    return new class($router, $scrambleAvailable) extends SpecGeneratorFactory
    {
        public function __construct(Router $router, private readonly bool $available)
        {
            parent::__construct($router);
        }

        protected function scrambleAvailable(): bool
        {
            return $this->available;
        }
    };
}

/**
 * Invoke make() with the given driver and otherwise unremarkable arguments.
 */
function resolveDriver(SpecGeneratorFactory $factory, string $driver): OpenApiSpecGenerator
{
    return $factory->make($driver, 'API', '1.0.0', null, null, [], 'api', 'api');
}

it('always resolves the native driver to a SpecGenerator', function (): void {
    foreach ([true, false] as $available) {
        $generator = resolveDriver(fakeFactory($available), 'native');

        expect($generator)
            ->toBeInstanceOf(SpecGenerator::class)
            ->toBeInstanceOf(OpenApiSpecGenerator::class);
    }
});

it('resolves auto to the native generator when Scramble is unavailable', function (): void {
    $generator = resolveDriver(fakeFactory(false), 'auto');

    expect($generator)->toBeInstanceOf(SpecGenerator::class);
});

it('resolves auto to the Scramble adapter when Scramble is available', function (): void {
    $generator = resolveDriver(fakeFactory(true), 'auto');

    expect($generator)->toBeInstanceOf(ScrambleSpecGenerator::class);
});

it('resolves scramble to the Scramble adapter when available', function (): void {
    $generator = resolveDriver(fakeFactory(true), 'scramble');

    expect($generator)->toBeInstanceOf(ScrambleSpecGenerator::class);
});

it('throws with a helpful message when scramble is requested but unavailable', function (): void {
    expect(fn (): OpenApiSpecGenerator => resolveDriver(fakeFactory(false), 'scramble'))
        ->toThrow(OpenApiException::class, SpecGeneratorFactory::MISSING_MESSAGE);
});

it('falls back to the native generator for an unrecognised driver', function (): void {
    $generator = resolveDriver(fakeFactory(false), 'bogus');

    expect($generator)->toBeInstanceOf(SpecGenerator::class);
});

it('exposes the documented missing-package message', function (): void {
    expect(SpecGeneratorFactory::MISSING_MESSAGE)
        ->toBe('The dedoc/scramble package is required for --driver=scramble. Install it with: composer require dedoc/scramble');
});

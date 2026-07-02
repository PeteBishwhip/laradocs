<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use Laradocs\Contracts\OpenApiSpecGenerator;
use Laradocs\OpenApi\Generator\ScrambleSpecGenerator;
use Laradocs\OpenApi\Generator\SpecGenerator;
use Laradocs\OpenApi\Generator\SpecGeneratorFactory;
use Laradocs\OpenApi\OpenApiException;

/**
 * A test-only subclass of the factory whose Scramble probe is pinned to a
 * constructor-injected bool. This lets every driver branch be exercised without
 * installing (or uninstalling) the optional dedoc/scramble package — the seam
 * {@see SpecGeneratorFactory::scrambleAvailable()} is left `protected` precisely
 * so tests can override it here instead of mocking `class_exists()`.
 */
final class FakeSpecGeneratorFactory extends SpecGeneratorFactory
{
    public function __construct(Router $router, private readonly bool $available)
    {
        parent::__construct($router);
    }

    protected function scrambleAvailable(): bool
    {
        return $this->available;
    }
}

/**
 * Build the pinned factory over the application's real router.
 */
function makeFakeFactory(bool $scrambleAvailable): SpecGeneratorFactory
{
    /** @var Router $router */
    $router = app('router');

    return new FakeSpecGeneratorFactory($router, $scrambleAvailable);
}

/**
 * Resolve the given driver with otherwise unremarkable arguments.
 */
function resolveGenerator(SpecGeneratorFactory $factory, string $driver): OpenApiSpecGenerator
{
    return $factory->make($driver, 'API', '1.0.0', null, null, [], 'api', 'api');
}

it('resolves the native driver to a SpecGenerator regardless of Scramble', function (): void {
    foreach ([true, false] as $available) {
        $generator = resolveGenerator(makeFakeFactory($available), 'native');

        expect($generator)
            ->toBeInstanceOf(SpecGenerator::class)
            ->toBeInstanceOf(OpenApiSpecGenerator::class);
    }
});

it('resolves auto to the native generator when Scramble is unavailable', function (): void {
    $generator = resolveGenerator(makeFakeFactory(false), 'auto');

    expect($generator)->toBeInstanceOf(SpecGenerator::class);
});

it('resolves auto to the Scramble adapter when Scramble is available', function (): void {
    $generator = resolveGenerator(makeFakeFactory(true), 'auto');

    expect($generator)->toBeInstanceOf(ScrambleSpecGenerator::class);
});

it('resolves scramble to the Scramble adapter when it is available', function (): void {
    $generator = resolveGenerator(makeFakeFactory(true), 'scramble');

    expect($generator)->toBeInstanceOf(ScrambleSpecGenerator::class);
});

it('throws with install instructions when scramble is requested but unavailable', function (): void {
    expect(fn (): OpenApiSpecGenerator => resolveGenerator(makeFakeFactory(false), 'scramble'))
        ->toThrow(OpenApiException::class, SpecGeneratorFactory::MISSING_MESSAGE);
});

it('confirms both generators satisfy the OpenApiSpecGenerator contract', function (): void {
    expect(class_implements(SpecGenerator::class))->toContain(OpenApiSpecGenerator::class)
        ->and(class_implements(ScrambleSpecGenerator::class))->toContain(OpenApiSpecGenerator::class);
});

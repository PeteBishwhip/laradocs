<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Laradocs\OpenApi\Generator\SpecGeneratorFactory;
use Laradocs\Tests\Fixtures\Api\OrderController;
use Symfony\Component\Yaml\Yaml;

beforeEach(function (): void {
    Route::middleware('api')->prefix('api')->group(function (): void {
        Route::get('orders', [OrderController::class, 'index']);
        Route::post('orders', [OrderController::class, 'store']);
        Route::get('orders/{order}', [OrderController::class, 'show']);
    });

    $this->output = sys_get_temp_dir() . '/laradocs-openapi-' . bin2hex(random_bytes(6)) . '/openapi.yaml';
});

afterEach(function (): void {
    if (is_file($this->output)) {
        unlink($this->output);
        @rmdir(dirname($this->output));
    }
});

it('registers the laradocs:openapi command', function (): void {
    expect(array_keys(Artisan::all()))->toContain('laradocs:openapi');
});

it('generates a spec file from the application routes', function (): void {
    $exit = Artisan::call('laradocs:openapi', ['--output' => $this->output]);

    expect($exit)->toBe(0)
        ->and(is_file($this->output))->toBeTrue();

    /** @var array<string, mixed> $spec */
    $spec = Yaml::parseFile($this->output);

    expect($spec['openapi'])->toBe('3.0.3')
        ->and($spec['paths'])->toHaveKeys(['/api/orders', '/api/orders/{order}'])
        ->and($spec['paths']['/api/orders'])->toHaveKeys(['get', 'post']);
});

it('refuses to overwrite an existing spec without --force', function (): void {
    @mkdir(dirname($this->output), 0777, true);
    file_put_contents($this->output, 'existing: true');

    $exit = Artisan::call('laradocs:openapi', ['--output' => $this->output]);

    expect($exit)->toBe(1)
        ->and(file_get_contents($this->output))->toBe('existing: true');

    $forced = Artisan::call('laradocs:openapi', ['--output' => $this->output, '--force' => true]);

    expect($forced)->toBe(0)
        ->and(file_get_contents($this->output))->not->toBe('existing: true');
});

it('honours the prefix filter option', function (): void {
    $exit = Artisan::call('laradocs:openapi', ['--output' => $this->output, '--prefix' => 'nope']);

    expect($exit)->toBe(0);

    /** @var array<string, mixed> $spec */
    $spec = Yaml::parseFile($this->output);

    expect($spec['paths'] ?? [])->toBe([]);
});

it('generates a spec with the native driver', function (): void {
    $exit = Artisan::call('laradocs:openapi', ['--output' => $this->output, '--driver' => 'native']);

    expect($exit)->toBe(0)
        ->and(is_file($this->output))->toBeTrue();

    /** @var array<string, mixed> $spec */
    $spec = Yaml::parseFile($this->output);

    expect($spec['openapi'])->toBe('3.0.3');
});

it('fails with install instructions when the scramble driver is requested but absent', function (): void {
    // Pin the factory's availability probe to false so the missing-package
    // path is exercised even on hosts (like CI) where Scramble IS installed.
    $this->app->bind(SpecGeneratorFactory::class, function (): SpecGeneratorFactory {
        return new class(app('router')) extends SpecGeneratorFactory
        {
            protected function scrambleAvailable(): bool
            {
                return false;
            }
        };
    });

    $exit = Artisan::call('laradocs:openapi', ['--output' => $this->output, '--driver' => 'scramble']);

    expect($exit)->toBe(1)
        ->and(Artisan::output())->toContain('dedoc/scramble')
        ->and(is_file($this->output))->toBeFalse();
});

it('uses the configured server url and output path when no options are given', function (): void {
    config()->set('laradocs.openapi.generator.server_url', 'https://api.example.test');
    config()->set('laradocs.openapi.generator.output', $this->output);

    $exit = Artisan::call('laradocs:openapi');

    expect($exit)->toBe(0)
        ->and(is_file($this->output))->toBeTrue();

    /** @var array<string, mixed> $spec */
    $spec = Yaml::parseFile($this->output);

    expect($spec['servers'])->toBe([['url' => 'https://api.example.test']]);
});

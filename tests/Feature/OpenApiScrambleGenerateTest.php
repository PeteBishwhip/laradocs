<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Laradocs\Tests\Fixtures\Api\OrderController;
use Symfony\Component\Yaml\Yaml;

/**
 * Full-stack integration coverage for the Scramble generator driver
 * (`laradocs:openapi --driver=scramble`). A workbench app exposing sample `api`
 * routes is scanned into a spec by dedoc/scramble, and that spec is asserted to
 * be a valid OpenAPI 3.1 document with at least one documented operation — then
 * fed back through the Pillar A reference pages as a render smoke test.
 *
 * dedoc/scramble is an optional dependency (CI installs it explicitly), so
 * every test skips (rather than fails) when it is absent — the guard resolves
 * the package by string via `class_exists()`, never through a hard `use
 * Dedoc\...` import, so this file loads cleanly on hosts without the package.
 */
beforeEach(function (): void {
    // Skip the whole suite when Scramble is absent so the suite stays green
    // without the package. Referenced by string only.
    if (! class_exists('\Dedoc\Scramble\Generator')) {
        $this->markTestSkipped('dedoc/scramble is not installed.');
    }

    Route::middleware('api')->prefix('api')->group(function (): void {
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    });

    // An empty docs root the generated spec is written into and Pillar A reads
    // from; `openapi.yaml` is a default `laradocs.openapi.files` candidate.
    $this->root = $this->makeDocs([]);
    $this->spec = $this->root . '/openapi.yaml';

    config()->set('laradocs.openapi.enabled', true);
});

it('generates an OpenAPI 3.1 spec through the scramble driver', function (): void {
    $exit = Artisan::call('laradocs:openapi', [
        '--driver' => 'scramble',
        '--output' => $this->spec,
        '--force' => true,
    ]);

    expect($exit)->toBe(0)
        ->and(is_file($this->spec))->toBeTrue();

    /** @var array<string, mixed> $spec */
    $spec = Yaml::parseFile($this->spec);

    // Scramble emits OpenAPI 3.1.x documents.
    expect($spec['openapi'])->toStartWith('3.1');

    // At least one path, and that path carries at least one operation.
    $paths = $spec['paths'] ?? [];
    expect($paths)->not->toBe([]);

    $operations = array_values($paths)[0];
    expect($operations)->toBeArray()->not->toBe([]);
});

it('carries the configured description and security through, and honours the middleware filter', function (): void {
    // An api-prefixed route that does NOT carry the api middleware must be
    // filtered out of the documented surface.
    Route::get('api/internal/ping', fn (): array => ['pong' => true]);

    config()->set('laradocs.openapi.generator.description', 'Warehouse endpoints.');
    config()->set('laradocs.openapi.generator.security', [['bearerAuth' => []]]);

    $exit = Artisan::call('laradocs:openapi', [
        '--driver' => 'scramble',
        '--output' => $this->spec,
        '--force' => true,
    ]);

    expect($exit)->toBe(0);

    /** @var array<string, mixed> $spec */
    $spec = Yaml::parseFile($this->spec);

    expect($spec['info']['description'] ?? '')->toContain('Warehouse endpoints.')
        ->and($spec['security'])->toBe([['bearerAuth' => []]])
        ->and(json_encode($spec['paths']))->not->toContain('internal');
});

it('renders the scramble-generated spec through the reference pages', function (): void {
    $exit = Artisan::call('laradocs:openapi', [
        '--driver' => 'scramble',
        '--output' => $this->spec,
        '--force' => true,
    ]);

    expect($exit)->toBe(0);

    // Pillar A render smoke test: the overview page mounts under the base slug
    // and renders without error from the Scramble-generated spec.
    $this->get('/docs/api')->assertOk();
});

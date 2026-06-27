<?php

declare(strict_types=1);

use cebe\openapi\Reader;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Laradocs\OpenApi\OpenApiParser;
use Laradocs\Tests\Fixtures\Api\OrderController;

/**
 * End-to-end coverage for the Pillar B generator (`laradocs:openapi`): a
 * workbench app exposing sample `api` routes — backed by a FormRequest and a
 * JsonResource — is scanned into a spec, and that emitted spec is fed straight
 * back through the read side. It must:
 *   - apply the {@see Laradocs\OpenApi\Generator\Attributes\ApiOperation}
 *     attribute / docblock overrides over the inferred values,
 *   - parse cleanly through {@see OpenApiParser} (US-001), and
 *   - render through the Pillar A reference pages.
 *
 * This closes the loop: generate → parse → render, proving the scaffold the
 * command writes is immediately usable, not just a dump.
 */
beforeEach(function (): void {
    if (! class_exists(Reader::class)) {
        $this->markTestSkipped('devizzent/cebe-php-openapi is not installed.');
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

/**
 * Run the generator against the workbench routes, writing the spec into the
 * docs root.
 */
function generateWorkbenchSpec(): int
{
    return Artisan::call('laradocs:openapi', ['--output' => test()->spec]);
}

it('generates a spec file from the workbench app routes', function (): void {
    expect(generateWorkbenchSpec())->toBe(0)
        ->and(is_file($this->spec))->toBeTrue();
});

it('parses the emitted spec cleanly through OpenApiParser', function (): void {
    generateWorkbenchSpec();

    $parser = new OpenApiParser(app('cache.store'), cacheEnabled: false);

    $normalized = $parser->parse($this->spec);

    expect($normalized->openApiVersion)->toBe('3.0.3');

    $operations = collect($normalized->operations);

    // The FormRequest-backed POST surfaces its inferred request body...
    $store = $operations->first(fn ($op) => $op->method === 'POST' && $op->path === '/api/orders');
    $body = $store->requestBody;
    expect($body)->not->toBe([]);
    $schema = $body['content']['application/json']['schema'];
    expect($schema['properties'])->toHaveKeys(['reference', 'quantity', 'email', 'status']);

    // ...and the JsonResource-backed GET surfaces its inferred response schema.
    $show = $operations->first(fn ($op) => $op->method === 'GET' && $op->path === '/api/orders/{order}');
    expect($show->responses)->toHaveKey('200');
});

it('applies attribute and docblock overrides over the inferred values', function (): void {
    generateWorkbenchSpec();

    $parser = new OpenApiParser(app('cache.store'), cacheEnabled: false);
    $operations = collect($parser->parse($this->spec)->operations);

    // index() carries an #[ApiOperation] attribute: summary, tags and the
    // deprecated flag all override the inferred values.
    $index = $operations->first(fn ($op) => $op->method === 'GET' && $op->path === '/api/orders');
    expect($index->summary)->toBe('List all orders')
        ->and($index->tags)->toContain('Orders')
        ->and($index->deprecated)->toBeTrue();

    // show() carries no attribute — its summary/description come from the
    // action docblock instead.
    $show = $operations->first(fn ($op) => $op->method === 'GET' && $op->path === '/api/orders/{order}');
    expect($show->summary)->toBe('Show a single order.')
        ->and($show->description)->toContain('identified by the given id')
        ->and($show->deprecated)->toBeFalse();
});

it('renders the emitted spec through the Pillar A reference pages', function (): void {
    generateWorkbenchSpec();

    // Overview page mounts under the base slug.
    $this->get('/docs/api')->assertOk();

    // The attribute-tagged operation nests under its overridden tag and shows
    // the overridden summary plus the deprecated marker.
    $index = $this->get('/docs/api/orders/ordersindex')->assertOk()->getContent();
    expect($index)
        ->toContain('GET')
        ->toContain('/api/orders')
        ->toContain('List all orders');

    // The docblock-described operation renders its summary and path parameter.
    $show = $this->get('/docs/api/order/ordersshow')->assertOk()->getContent();
    expect($show)
        ->toContain('Show a single order')
        ->toContain('order')          // the {order} path parameter
        ->toContain('Responses');

    // The FormRequest-backed POST renders its request body.
    $store = $this->get('/docs/api/order/ordersstore')->assertOk()->getContent();
    expect($store)
        ->toContain('POST')
        ->toContain('Request Body')
        ->toContain('reference');
});

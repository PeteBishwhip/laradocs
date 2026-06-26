<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Laradocs\OpenApi\Generator\RequestInspector;
use Laradocs\OpenApi\Generator\ResponseInspector;
use Laradocs\OpenApi\Generator\RouteCollector;
use Laradocs\OpenApi\Generator\RuleMapper;
use Laradocs\OpenApi\Generator\SpecGenerator;
use Laradocs\Tests\Fixtures\Api\OrderController;
use Symfony\Component\Yaml\Yaml;

/**
 * Register the fixture API routes plus a non-API route on the shared router.
 */
function defineApiRoutes(): Router
{
    Route::middleware('api')->prefix('api')->group(function (): void {
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
        Route::get('orders/search', [OrderController::class, 'search'])->name('orders.search');
    });

    // A web route that must NOT be collected.
    Route::get('dashboard', fn () => 'home')->name('dashboard');

    /** @var Router $router */
    $router = app('router');

    return $router;
}

it('enumerates and filters routes by prefix and middleware', function (): void {
    $router = defineApiRoutes();

    $routes = (new RouteCollector($router, 'api', 'api'))->collect();

    $uris = array_map(fn ($route) => $route->uri, $routes);

    expect($uris)->toContain('/api/orders', '/api/orders/{order}', '/api/orders/search')
        ->not->toContain('/dashboard');
});

it('derives method, path and path parameters', function (): void {
    $router = defineApiRoutes();

    $routes = (new RouteCollector($router, 'api', 'api'))->collect();

    $show = collect($routes)->firstWhere('uri', '/api/orders/{order}');

    expect($show)->not->toBeNull()
        ->and($show->methods)->toBe(['GET'])
        ->and($show->pathParameters)->toBe(['order'])
        ->and($show->controller)->toBe(OrderController::class)
        ->and($show->action)->toBe('show');

    // HEAD is stripped from a GET route.
    expect($show->methods)->not->toContain('HEAD');
});

it('respects the prefix filter', function (): void {
    $router = defineApiRoutes();

    $none = (new RouteCollector($router, 'v2', 'api'))->collect();

    expect($none)->toBe([]);
});

it('maps Laravel validation rules to JSON Schema', function (): void {
    $mapper = new RuleMapper;

    expect($mapper->map('required|string|max:32'))
        ->toMatchArray(['required' => true])
        ->and($mapper->map('required|string|max:32')['schema'])
        ->toMatchArray(['type' => 'string', 'maxLength' => 32]);

    expect($mapper->map('integer|min:1|max:99')['schema'])
        ->toMatchArray(['type' => 'integer', 'minimum' => 1, 'maximum' => 99]);

    expect($mapper->map('required|email')['schema'])
        ->toMatchArray(['type' => 'string', 'format' => 'email']);

    expect($mapper->map('in:pending,paid,shipped')['schema']['enum'])
        ->toBe(['pending', 'paid', 'shipped']);

    expect($mapper->map('nullable|string')['schema'])
        ->toMatchArray(['type' => 'string', 'nullable' => true]);

    expect($mapper->map(['required', 'boolean'])['schema']['type'])->toBe('boolean');
});

it('infers a request body schema from a FormRequest', function (): void {
    $router = defineApiRoutes();

    $route = collect((new RouteCollector($router, 'api', 'api'))->collect())
        ->firstWhere('action', 'store');

    $request = (new RequestInspector)->inspect($route);

    expect($request['properties'])->toHaveKeys(['reference', 'quantity', 'email', 'status', 'notes', 'items'])
        ->and($request['required'])->toContain('reference', 'quantity', 'email', 'status')
        ->and($request['required'])->not->toContain('notes', 'items')
        // Dotted array keys flatten to their leading segment.
        ->and($request['properties'])->not->toHaveKey('items.*.sku');

    expect($request['properties']['quantity'])->toMatchArray(['type' => 'integer', 'minimum' => 1]);
});

it('infers params from a detectable inline validate() call', function (): void {
    $router = defineApiRoutes();

    $route = collect((new RouteCollector($router, 'api', 'api'))->collect())
        ->firstWhere('action', 'search');

    $request = (new RequestInspector)->inspect($route);

    expect($request['properties'])->toHaveKeys(['term', 'limit'])
        ->and($request['required'])->toContain('term')
        ->and($request['properties']['limit'])->toMatchArray(['type' => 'integer', 'maximum' => 50]);
});

it('derives a response schema from a JsonResource return type', function (): void {
    $router = defineApiRoutes();

    $route = collect((new RouteCollector($router, 'api', 'api'))->collect())
        ->firstWhere('action', 'show');

    $response = (new ResponseInspector)->inspect($route);

    expect($response['status'])->toBe('200')
        ->and($response['schema']['type'])->toBe('object')
        ->and($response['schema']['properties'])->toHaveKeys(['id', 'reference', 'status']);
});

it('derives an array response schema from a ResourceCollection', function (): void {
    $router = defineApiRoutes();

    $route = collect((new RouteCollector($router, 'api', 'api'))->collect())
        ->firstWhere('action', 'index');

    $response = (new ResponseInspector)->inspect($route);

    expect($response['schema']['type'])->toBe('array')
        ->and($response['schema']['items']['type'])->toBe('object')
        ->and($response['schema']['items']['properties'])->toHaveKeys(['id', 'reference', 'status']);
});

it('uses a 201 status for POST-only routes', function (): void {
    $router = defineApiRoutes();

    $route = collect((new RouteCollector($router, 'api', 'api'))->collect())
        ->firstWhere('action', 'store');

    expect((new ResponseInspector)->inspect($route)['status'])->toBe('201');
});

it('assembles a complete OpenAPI document', function (): void {
    $router = defineApiRoutes();

    $spec = (new SpecGenerator(
        routes: new RouteCollector($router, 'api', 'api'),
        requests: new RequestInspector,
        responses: new ResponseInspector,
        title: 'Orders API',
        version: '2.1.0',
        serverUrl: 'https://example.test/',
    ))->generate();

    expect($spec['openapi'])->toBe('3.0.3')
        ->and($spec['info'])->toBe(['title' => 'Orders API', 'version' => '2.1.0'])
        ->and($spec['servers'])->toBe([['url' => 'https://example.test']])
        ->and($spec['paths'])->toHaveKeys(['/api/orders', '/api/orders/{order}']);

    $show = $spec['paths']['/api/orders/{order}']['get'];

    expect($show['parameters'][0])->toMatchArray(['name' => 'order', 'in' => 'path', 'required' => true]);

    $store = $spec['paths']['/api/orders']['post'];

    expect($store['requestBody']['content']['application/json']['schema']['type'])->toBe('object')
        ->and($store['responses'])->toHaveKey('201');

    // The whole document round-trips through the YAML dumper/parser.
    $yaml = Yaml::dump($spec, 8, 2);

    expect(Yaml::parse($yaml))->toBe($spec);
});

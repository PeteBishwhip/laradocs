<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Laradocs\OpenApi\Generator\AttributeReader;
use Laradocs\OpenApi\Generator\CollectedRoute;
use Laradocs\OpenApi\Generator\MethodSource;
use Laradocs\OpenApi\Generator\RequestInspector;
use Laradocs\OpenApi\Generator\ResponseInspector;
use Laradocs\OpenApi\Generator\RouteCollector;
use Laradocs\OpenApi\Generator\RuleMapper;
use Laradocs\OpenApi\Generator\SpecGenerator;
use Laradocs\Tests\Fixtures\Api\CoverageController;
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
    Route::get('dashboard', function () {
        return 'home';
    })->name('dashboard');

    /** @var Router $router */
    $router = app('router');

    return $router;
}

it('enumerates and filters routes by prefix and middleware', function (): void {
    $router = defineApiRoutes();

    $routes = (new RouteCollector($router, 'api', 'api'))->collect();

    $uris = array_map(function ($route) {
        return $route->uri;
    }, $routes);

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
        new RouteCollector($router, 'api', 'api'),
        new RequestInspector,
        new ResponseInspector,
        'Orders API',
        '2.1.0',
        'https://example.test/',
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

it('maps the remaining scalar rule types to JSON Schema', function (): void {
    $mapper = new RuleMapper;

    expect($mapper->map('numeric')['schema'])->toMatchArray(['type' => 'number'])
        ->and($mapper->map('url')['schema'])->toMatchArray(['type' => 'string', 'format' => 'uri'])
        ->and($mapper->map('uuid')['schema'])->toMatchArray(['type' => 'string', 'format' => 'uuid'])
        ->and($mapper->map('ulid')['schema'])->toMatchArray(['type' => 'string'])
        ->and($mapper->map('date')['schema'])->toMatchArray(['type' => 'string', 'format' => 'date-time']);
});

it('maps numeric, array and non-numeric bound rules', function (): void {
    $mapper = new RuleMapper;

    // Float bound on a numeric type.
    expect($mapper->map('numeric|min:1.5')['schema'])->toMatchArray(['type' => 'number', 'minimum' => 1.5])
        // Item-count bounds on an array type.
        ->and($mapper->map('array|min:2|max:5')['schema'])->toMatchArray(['type' => 'array', 'minItems' => 2, 'maxItems' => 5])
        // A non-numeric bound argument is ignored entirely.
        ->and($mapper->map('string|min:abc')['schema'])->toBe(['type' => 'string']);
});

it('ignores unrecognised rule tokens and defaults to a string schema', function (): void {
    $mapper = new RuleMapper;

    // A rule with no schema meaning (e.g. `sometimes`, `confirmed`) hits the
    // switch's default branch and leaves the schema at its string fallback.
    expect($mapper->map('sometimes|confirmed')['schema'])->toBe(['type' => 'string']);
});

it('reads a method source and returns null for sourceless methods', function (): void {
    $source = MethodSource::read(new ReflectionMethod(OrderController::class, 'search'));

    expect($source)->toContain('validate');

    // An internal method has no source file, so its getFileName() is false.
    expect(MethodSource::read(new ReflectionMethod(ArrayObject::class, 'count')))->toBeNull();
});

/**
 * Build a CollectedRoute pointing at a fixture controller action.
 */
function coverageRoute(string $action, string $method = 'GET'): CollectedRoute
{
    return new CollectedRoute(
        [$method],
        '/api/x',
        [],
        CoverageController::class,
        $action,
    );
}

it('applies docblock overrides and ignores unresolvable routes', function (): void {
    $reader = new AttributeReader;

    // No controller/action -> nothing to read.
    expect($reader->read(new CollectedRoute(['GET'], '/x', [])))->toBe([]);

    // Controller present but the action does not exist.
    expect($reader->read(coverageRoute('missing')))->toBe([]);

    // A docblock with a @deprecated tag, summary and description.
    expect($reader->read(coverageRoute('deprecatedDocblock')))->toMatchArray([
        'summary' => 'Legacy lookup.',
        'description' => 'Kept only for backwards compatibility.',
        'deprecated' => true,
    ]);

    // PHP attributes are intentionally unavailable in the PHP 7.3 backport.
    expect($reader->read(coverageRoute('fullyAttributed')))->toBe([]);
});

it('degrades gracefully for unresolvable or unusual request inputs', function (): void {
    $inspect = function (string $action): array {
        return (new RequestInspector)->inspect(coverageRoute($action, 'POST'));
    };

    // Null controller/action.
    expect((new RequestInspector)->inspect(new CollectedRoute(['POST'], '/x', [])))
        ->toBe(['properties' => [], 'required' => []])
        // Missing action method.
        ->and($inspect('missing'))->toBe(['properties' => [], 'required' => []])
        // FormRequest without a rules() method.
        ->and($inspect('noRules'))->toBe(['properties' => [], 'required' => []])
        // FormRequest whose rules() throws.
        ->and($inspect('throwing'))->toBe(['properties' => [], 'required' => []]);

    // A ruleset value that is neither a string nor an array is skipped.
    $weird = $inspect('weird');
    expect($weird['properties'])->toHaveKey('name')->not->toHaveKey('count');
});

it('degrades gracefully for unresolvable or non-resource responses', function (): void {
    $inspect = function (string $action): array {
        return (new ResponseInspector)->inspect(coverageRoute($action));
    };

    expect((new ResponseInspector)->inspect(new CollectedRoute(['GET'], '/x', []))['schema'])->toBeNull()
        ->and($inspect('missing')['schema'])->toBeNull()
        // A builtin (array) return type.
        ->and($inspect('returnsArray')['schema'])->toBeNull()
        // No return type at all.
        ->and($inspect('noReturn')['schema'])->toBeNull()
        // A class that is not a JsonResource.
        ->and($inspect('returnsJson')['schema'])->toBeNull();

    // A ResourceCollection without $collects yields an array of empty objects.
    $bare = $inspect('returnsBareCollection');
    expect($bare['schema']['type'])->toBe('array')
        ->and($bare['schema']['items'])->toBe(['type' => 'object']);
});

it('handles closure routes and empty request bodies in the assembled document', function (): void {
    Route::middleware('api')->prefix('api')->group(function (): void {
        Route::get('ping', function () {
            return 'pong';
        });
        Route::post('empty', [CoverageController::class, 'emptyPost']);
    });

    /** @var Router $router */
    $router = app('router');

    $spec = (new SpecGenerator(
        new RouteCollector($router, 'api', 'api'),
        new RequestInspector,
        new ResponseInspector,
    ))->generate();

    // A closure route has no action/controller, so summary and tag fall back.
    $ping = $spec['paths']['/api/ping']['get'];
    expect($ping['summary'])->toBe('GET /api/ping')
        ->and($ping['tags'])->toBe(['Default']);

    // A POST with no inferable input emits no requestBody.
    expect($spec['paths']['/api/empty']['post'])->not->toHaveKey('requestBody');
});

it('excludes routes failing the middleware filter and verb-only routes', function (): void {
    // Prefix matches but the api middleware is absent.
    Route::prefix('api')->get('plain', function () {
        return 'x';
    });
    // Only OPTIONS, which is stripped, leaving no documentable verb.
    Route::middleware('api')->prefix('api')->match(['OPTIONS'], 'opts', function () {
        return 'x';
    });

    /** @var Router $router */
    $router = app('router');

    $uris = array_map(function ($route) {
        return $route->uri;
    }, (new RouteCollector($router, 'api', 'api'))->collect());

    expect($uris)->not->toContain('/api/plain')
        ->and($uris)->not->toContain('/api/opts');
});

<?php

declare(strict_types=1);

namespace Laradocs\OpenApi\Generator;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

/**
 * Enumerates the application's registered routes and reduces the ones that
 * look like API endpoints to {@see CollectedRoute} value objects.
 *
 * Routes are filtered by an optional URI prefix and/or middleware name (both
 * default to `api` in config) so only the intended surface lands in the spec.
 * HEAD/OPTIONS verbs are dropped — they are framework-synthesised mirrors of
 * GET and never carry their own documentation.
 */
final class RouteCollector
{
    /**
     * @readonly
     * @var \Illuminate\Routing\Router
     */
    private $router;
    /**
     * @var string|null
     * @readonly
     */
    private $prefix = 'api';
    /**
     * @var string|null
     * @readonly
     */
    private $middleware = 'api';
    /**
     * @param  string|null  $prefix  URI prefix routes must start with (null disables the filter).
     * @param  string|null  $middleware  Middleware name routes must carry (null disables the filter).
     */
    public function __construct(Router $router, ?string $prefix = 'api', ?string $middleware = 'api')
    {
        $this->router = $router;
        $this->prefix = $prefix;
        $this->middleware = $middleware;
    }

    /**
     * @return array<int, CollectedRoute>
     */
    public function collect(): array
    {
        $collected = [];

        /** @var array<int, Route> $routes */
        $routes = $this->router->getRoutes()->getRoutes();

        foreach ($routes as $route) {
            if (! $this->matches($route)) {
                continue;
            }

            $methods = $this->methods($route);

            if ($methods === []) {
                continue;
            }

            $collected[] = new CollectedRoute(
                $methods,
                $this->uri($route),
                $this->pathParameters($route),
                $this->controller($route),
                $this->action($route),
                $route->getName(),
            );
        }

        return $collected;
    }

    private function controller(Route $route): ?string
    {
        $action = $route->getActionName();

        if ($action === 'Closure' || $action === '') {
            return null;
        }

        $controller = explode('@', $action, 2)[0];

        return $controller === '' ? null : ltrim($controller, '\\');
    }

    private function matches(Route $route): bool
    {
        if ($this->prefix !== null && $this->prefix !== '') {
            $needle = trim($this->prefix, '/');

            if (strncmp(trim($route->uri(), '/'), $needle, strlen($needle)) !== 0) {
                return false;
            }
        }

        if ($this->middleware !== null && $this->middleware !== '' && ! in_array($this->middleware, $route->gatherMiddleware(), true)) {
            return false;
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function methods(Route $route): array
    {
        $methods = [];

        foreach (array_filter($route->methods(), 'is_string') as $method) {
            $method = strtoupper($method);

            if ($method === 'HEAD' || $method === 'OPTIONS') {
                continue;
            }

            $methods[] = $method;
        }

        return $methods;
    }

    /**
     * @return array<int, string>
     */
    private function pathParameters(Route $route): array
    {
        $names = [];

        foreach ($route->parameterNames() as $name) {
            if (is_string($name)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Normalise a Laravel URI to an OpenAPI path: a single leading slash and
     * `{param}` placeholders preserved verbatim.
     */
    private function uri(Route $route): string
    {
        return '/' . ltrim($route->uri(), '/');
    }

    private function action(Route $route): ?string
    {
        if ($this->controller($route) === null) {
            return null;
        }

        $method = $route->getActionMethod();

        // Single-action / invokable controllers report `__invoke`.
        return $method === '' ? '__invoke' : $method;
    }
}

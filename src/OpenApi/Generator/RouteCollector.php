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
     * @param  string|null  $prefix  URI prefix routes must start with (null disables the filter).
     * @param  string|null  $middleware  Middleware name routes must carry (null disables the filter).
     */
    public function __construct(
        private readonly Router $router,
        private readonly ?string $prefix = 'api',
        private readonly ?string $middleware = 'api',
    ) {}

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
                methods: $methods,
                uri: $this->uri($route),
                pathParameters: $this->pathParameters($route),
                controller: $route->getControllerClass() === null
                    ? null
                    : ltrim($route->getControllerClass(), '\\'),
                action: $this->action($route),
                name: $route->getName(),
            );
        }

        return $collected;
    }

    private function matches(Route $route): bool
    {
        if ($this->prefix !== null && $this->prefix !== '') {
            $needle = trim($this->prefix, '/');

            if (! str_starts_with(trim($route->uri(), '/'), $needle)) {
                return false;
            }
        }

        if ($this->middleware !== null && $this->middleware !== '') {
            if (! in_array($this->middleware, $route->gatherMiddleware(), true)) {
                return false;
            }
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
        if ($route->getControllerClass() === null) {
            return null;
        }

        $method = $route->getActionMethod();

        // Single-action / invokable controllers report `__invoke`.
        return $method === '' ? '__invoke' : $method;
    }
}

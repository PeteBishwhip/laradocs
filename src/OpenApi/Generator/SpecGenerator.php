<?php

declare(strict_types=1);

namespace Laradocs\OpenApi\Generator;

use Illuminate\Support\Str;

/**
 * Assembles a complete OpenAPI 3.0 document (as a plain nested array) from the
 * routes discovered by {@see RouteCollector}, the inputs inferred by
 * {@see RequestInspector}, and the responses inferred by {@see ResponseInspector}.
 *
 * The output is a hand-tweakable scaffold, not a finished spec — it captures the
 * route surface and whatever schema could be reflected, leaving descriptions and
 * fine detail for the developer.
 */
final class SpecGenerator
{
    public function __construct(
        private readonly RouteCollector $routes,
        private readonly RequestInspector $requests,
        private readonly ResponseInspector $responses,
        private readonly string $title = 'API',
        private readonly string $version = '1.0.0',
        private readonly ?string $serverUrl = null,
        private readonly AttributeReader $attributes = new AttributeReader,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generate(): array
    {
        $spec = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => $this->title,
                'version' => $this->version,
            ],
        ];

        if ($this->serverUrl !== null && $this->serverUrl !== '') {
            $spec['servers'] = [['url' => rtrim($this->serverUrl, '/')]];
        }

        $spec['paths'] = $this->paths();

        return $spec;
    }

    /**
     * @return array<string, mixed>
     */
    private function paths(): array
    {
        $paths = [];

        foreach ($this->routes->collect() as $route) {
            $path = $this->openApiPath($route->uri);
            $operation = $this->operation($route);

            foreach ($route->methods as $method) {
                $paths[$path][strtolower($method)] = $operation;
            }
        }

        ksort($paths);

        return $paths;
    }

    /**
     * @return array<string, mixed>
     */
    private function operation(CollectedRoute $route): array
    {
        $overrides = $this->attributes->read($route);

        $operation = ['summary' => $overrides['summary'] ?? $this->summary($route)];

        if (isset($overrides['description']) && $overrides['description'] !== '') {
            $operation['description'] = $overrides['description'];
        }

        $operation['operationId'] = $overrides['operationId'] ?? $this->operationId($route);
        $operation['tags'] = $overrides['tags'] ?? [$this->tag($route)];

        if ($overrides['deprecated'] ?? false) {
            $operation['deprecated'] = true;
        }

        $parameters = $this->pathParameters($route);
        $request = $this->requests->inspect($route);

        if ($this->hasBody($route)) {
            $body = $this->requestBody($request);

            if ($body !== null) {
                $operation['requestBody'] = $body;
            }
        } else {
            $parameters = array_merge($parameters, $this->queryParameters($request));
        }

        if ($parameters !== []) {
            $operation['parameters'] = $parameters;
        }

        $operation['responses'] = $this->responses($route);

        return $operation;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pathParameters(CollectedRoute $route): array
    {
        $parameters = [];

        foreach ($route->pathParameters as $name) {
            $parameters[] = [
                'name' => $name,
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'string'],
            ];
        }

        return $parameters;
    }

    /**
     * @param  array{properties: array<string, array<string, mixed>>, required: array<int, string>}  $request
     * @return array<int, array<string, mixed>>
     */
    private function queryParameters(array $request): array
    {
        $parameters = [];

        foreach ($request['properties'] as $name => $schema) {
            $parameters[] = [
                'name' => $name,
                'in' => 'query',
                'required' => in_array($name, $request['required'], true),
                'schema' => $schema,
            ];
        }

        return $parameters;
    }

    /**
     * @param  array{properties: array<string, array<string, mixed>>, required: array<int, string>}  $request
     * @return array<string, mixed>|null
     */
    private function requestBody(array $request): ?array
    {
        if ($request['properties'] === []) {
            return null;
        }

        $schema = [
            'type' => 'object',
            'properties' => $request['properties'],
        ];

        if ($request['required'] !== []) {
            $schema['required'] = $request['required'];
        }

        return [
            'required' => true,
            'content' => [
                'application/json' => ['schema' => $schema],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function responses(CollectedRoute $route): array
    {
        $response = $this->responses->inspect($route);

        $body = ['description' => 'Successful response'];

        if ($response['schema'] !== null) {
            $body['content'] = [
                'application/json' => ['schema' => $response['schema']],
            ];
        }

        return [$response['status'] => $body];
    }

    private function hasBody(CollectedRoute $route): bool
    {
        foreach ($route->methods as $method) {
            if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert a Laravel URI (`api/users/{user}`) to an OpenAPI path
     * (`/api/users/{user}`). The placeholder syntax already matches.
     */
    private function openApiPath(string $uri): string
    {
        // Strip Laravel's optional-parameter `?` marker: `{user?}` -> `{user}`.
        $path = preg_replace('/\{(\w+)\?\}/', '{$1}', $uri);

        return '/' . ltrim($path ?? $uri, '/');
    }

    private function summary(CollectedRoute $route): string
    {
        if ($route->action !== null && $route->action !== '__invoke') {
            return Str::of($route->action)->headline()->toString();
        }

        return strtoupper(implode('/', $route->methods)) . ' ' . $route->uri;
    }

    private function operationId(CollectedRoute $route): string
    {
        if ($route->name !== null && $route->name !== '') {
            return Str::camel(str_replace(['.', '-'], ' ', $route->name));
        }

        $method = strtolower($route->methods[0] ?? 'get');

        return Str::camel($method . ' ' . str_replace(['/', '{', '}', '-'], ' ', $route->uri));
    }

    private function tag(CollectedRoute $route): string
    {
        if ($route->controller !== null) {
            return Str::of(class_basename($route->controller))
                ->beforeLast('Controller')
                ->headline()
                ->toString() ?: 'Default';
        }

        return 'Default';
    }
}

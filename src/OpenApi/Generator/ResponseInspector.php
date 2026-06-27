<?php

declare(strict_types=1);

namespace Laradocs\OpenApi\Generator;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Infers a route's success-response schema from its controller action's return
 * type-hint.
 *
 * When the action returns a {@see JsonResource} (or a {@see ResourceCollection}),
 * the resource's `toArray()` method is scraped for its top-level keys to build
 * an object schema. A collection wraps that object in an `array` schema. The
 * success status defaults to 200, or 201 for routes that only answer POST.
 *
 * The shape is intentionally conservative — only the field *names* are
 * recoverable from `toArray()` source, so every property defaults to
 * `type: string` for the developer to refine.
 */
final class ResponseInspector
{
    /**
     * @return array{status: string, schema: array<string, mixed>|null}
     */
    public function inspect(CollectedRoute $route): array
    {
        $status = $this->successStatus($route);
        $resource = $this->resourceReturnType($route);

        if ($resource === null) {
            return ['status' => $status, 'schema' => null];
        }

        return ['status' => $status, 'schema' => $this->schemaFor($resource)];
    }

    private function successStatus(CollectedRoute $route): string
    {
        return $route->methods === ['POST'] ? '201' : '200';
    }

    private function reflect(CollectedRoute $route): ?ReflectionMethod
    {
        if ($route->controller === null || $route->action === null) {
            return null;
        }

        if (! method_exists($route->controller, $route->action)) {
            return null;
        }

        return new ReflectionMethod($route->controller, $route->action);
    }

    /**
     * @return class-string|null The returned resource class, or null when the action returns something else.
     */
    private function resourceReturnType(CollectedRoute $route): ?string
    {
        $method = $this->reflect($route);

        if ($method === null) {
            return null;
        }

        $type = $method->getReturnType();

        if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        $class = $type->getName();

        return is_subclass_of($class, JsonResource::class) || $class === JsonResource::class
            ? $class
            : null;
    }

    /**
     * @param  class-string  $resource
     * @return array<string, mixed>
     */
    private function schemaFor(string $resource): array
    {
        if (is_subclass_of($resource, ResourceCollection::class)) {
            return [
                'type' => 'array',
                'items' => $this->objectSchema($this->collectedResource($resource)),
            ];
        }

        return $this->objectSchema($resource);
    }

    /**
     * Resolve the resource a collection wraps via its `$collects` property,
     * falling back to the collection class itself.
     *
     * @param  class-string  $collection
     * @return class-string|null
     */
    private function collectedResource(string $collection): ?string
    {
        $property = (new ReflectionClass($collection))->getProperty('collects');
        $default = $property->getDefaultValue();

        if (is_string($default) && class_exists($default)) {
            /** @var class-string $default */
            return $default;
        }

        return null;
    }

    /**
     * @param  class-string|null  $resource
     * @return array<string, mixed>
     */
    private function objectSchema(?string $resource): array
    {
        $properties = [];

        foreach ($this->toArrayKeys($resource) as $key) {
            $properties[$key] = ['type' => 'string'];
        }

        $schema = ['type' => 'object'];

        if ($properties !== []) {
            $schema['properties'] = $properties;
        }

        return $schema;
    }

    /**
     * Scrape the top-level array keys returned by a resource's `toArray()`.
     *
     * @param  class-string|null  $resource
     * @return array<int, string>
     */
    private function toArrayKeys(?string $resource): array
    {
        if ($resource === null || ! method_exists($resource, 'toArray')) {
            return [];
        }

        $source = MethodSource::read(new ReflectionMethod($resource, 'toArray')) ?? '';

        $keys = [];

        if (preg_match_all('/([\'"])([A-Za-z_]\w*)\1\s*=>/', $source, $matches)) {
            foreach ($matches[2] as $key) {
                if (! in_array($key, $keys, true)) {
                    $keys[] = $key;
                }
            }
        }

        return $keys;
    }
}

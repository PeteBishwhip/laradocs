<?php

declare(strict_types=1);

namespace Laradocs\OpenApi;

use cebe\openapi\Reader;
use cebe\openapi\ReferenceContext;
use cebe\openapi\spec\OpenApi;
use Illuminate\Contracts\Cache\Repository;
use Laradocs\Support\CacheKey;
use RuntimeException;

/**
 * Parses an OpenAPI 3.0/3.1 spec file into a {@see NormalizedSpec}.
 *
 * The spec is read with devizzent/cebe-php-openapi, validated, and its `$ref`s
 * resolved (recursive references are left as `$ref` markers to keep the result
 * finite). The cebe object graph is then reduced to plain arrays/value objects
 * so nothing downstream depends on the library. The normalized array is cached
 * by spec path + mtime, so an unchanged spec parses once and a changed file
 * busts the entry automatically.
 *
 * Construction does not require the cebe library, but {@see parse()} does; gate
 * calls behind `class_exists(\cebe\openapi\Reader::class)`.
 */
final class OpenApiParser
{
    public function __construct(
        private readonly Repository $cache,
        private readonly bool $cacheEnabled = true,
        private readonly ?int $ttl = null,
    ) {}

    /**
     * Parse the spec at the given path into a normalized, cache-safe value
     * object. Cached by path + mtime; pass a spec whose mtime has changed to
     * force a fresh parse.
     *
     * @throws RuntimeException when the cebe library is unavailable, the file
     *                          cannot be read, or the spec fails validation.
     */
    public function parse(string $path): NormalizedSpec
    {
        if (! class_exists(Reader::class)) {
            throw new RuntimeException(
                'Parsing OpenAPI specs requires the devizzent/cebe-php-openapi package.',
            );
        }

        if (! is_file($path)) {
            throw new RuntimeException("OpenAPI spec not found at: {$path}");
        }

        $key = CacheKey::openApi($path, (int) filemtime($path));

        if ($this->cacheEnabled && $this->cache->has($key)) {
            $cached = $this->cache->get($key);

            if (is_array($cached)) {
                return NormalizedSpec::fromArray(Coerce::assoc($cached));
            }
        }

        $normalized = $this->normalize($this->read($path));

        if ($this->cacheEnabled) {
            if ($this->ttl === null) {
                $this->cache->forever($key, $normalized->toArray());
            } else {
                $this->cache->put($key, $normalized->toArray(), $this->ttl);
            }
        }

        return $normalized;
    }

    /**
     * Read and validate the spec file, resolving references.
     */
    private function read(string $path): OpenApi
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $spec = in_array($extension, ['yaml', 'yml'], true)
            ? Reader::readFromYamlFile($path, OpenApi::class, ReferenceContext::RESOLVE_MODE_ALL)
            : Reader::readFromJsonFile($path, OpenApi::class, ReferenceContext::RESOLVE_MODE_ALL);

        if (! $spec->validate()) {
            throw new RuntimeException(
                'Invalid OpenAPI spec: ' . implode('; ', $spec->getErrors()),
            );
        }

        return $spec;
    }

    /**
     * Reduce the cebe object graph to plain arrays and our own value objects.
     */
    private function normalize(OpenApi $spec): NormalizedSpec
    {
        $data = Coerce::assoc($this->toArray($spec->getSerializableData()));
        $components = Coerce::assoc($data['components'] ?? []);
        $schemaNodes = [];

        foreach (Coerce::assoc($components['schemas'] ?? []) as $name => $schema) {
            $schemaNodes[(string) $name] = new SchemaNode((string) $name, Coerce::assoc($schema));
        }

        return new NormalizedSpec(
            openApiVersion: Coerce::string($data['openapi'] ?? ''),
            info: Coerce::assoc($data['info'] ?? []),
            servers: array_values(Coerce::listOfAssoc($data['servers'] ?? [])),
            tags: array_values(Coerce::listOfAssoc($data['tags'] ?? [])),
            operations: $this->operations(Coerce::assoc($data['paths'] ?? [])),
            schemas: $schemaNodes,
        );
    }

    /**
     * Flatten the spec's paths object into a list of operations.
     *
     * @param  array<string, mixed>  $paths
     * @return array<int, Operation>
     */
    private function operations(array $paths): array
    {
        $methods = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];
        $operations = [];

        foreach ($paths as $path => $pathItem) {
            $pathItem = Coerce::assoc($pathItem);

            foreach ($methods as $method) {
                if (! isset($pathItem[$method]) || ! is_array($pathItem[$method])) {
                    continue;
                }

                $operation = Coerce::assoc($pathItem[$method]);

                $operations[] = new Operation(
                    method: strtoupper($method),
                    path: (string) $path,
                    operationId: Coerce::nullableString($operation['operationId'] ?? null),
                    summary: Coerce::nullableString($operation['summary'] ?? null),
                    description: Coerce::nullableString($operation['description'] ?? null),
                    tags: array_values(Coerce::stringList($operation['tags'] ?? [])),
                    parameters: array_values(Coerce::listOfAssoc($operation['parameters'] ?? [])),
                    requestBody: Coerce::assoc($operation['requestBody'] ?? []),
                    responses: Coerce::assoc($operation['responses'] ?? []),
                    deprecated: Coerce::bool($operation['deprecated'] ?? false),
                );
            }
        }

        return $operations;
    }

    /**
     * Recursively cast the cebe serialisable structure (nested stdClass/array)
     * into plain PHP arrays.
     */
    private function toArray(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            $value = (array) $value;
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->toArray($item), $value);
        }

        return $value;
    }
}

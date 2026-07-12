<?php

declare(strict_types=1);

namespace Laradocs\OpenApi;

use cebe\openapi\Reader;
use cebe\openapi\ReferenceContext;
use cebe\openapi\spec\OpenApi;
use Illuminate\Contracts\Cache\Repository;
use Laradocs\Support\CacheKey;

/**
 * Parses an OpenAPI 3.0/3.1 spec file into a {@see NormalizedSpec}.
 *
 * The spec is read with devizzent/cebe-php-openapi, validated, and its `$ref`s
 * resolved (recursive references are left as `$ref` markers to keep the result
 * finite). The cebe object graph is then reduced to plain arrays/value objects
 * so nothing downstream depends on the library. The normalized array is cached
 * by spec path + mtime, so an unchanged spec parses once and a changed file
 * busts the entry automatically.
 */
final class OpenApiParser
{
    /**
     * @readonly
     * @var \Illuminate\Contracts\Cache\Repository
     */
    private $cache;
    /**
     * @readonly
     * @var bool
     */
    private $cacheEnabled = true;
    /**
     * @readonly
     * @var int|null
     */
    private $ttl;
    public function __construct(Repository $cache, bool $cacheEnabled = true, ?int $ttl = null)
    {
        $this->cache = $cache;
        $this->cacheEnabled = $cacheEnabled;
        $this->ttl = $ttl;
    }

    /**
     * Parse the spec at the given path into a normalized, cache-safe value
     * object. Cached by path + mtime; pass a spec whose mtime has changed to
     * force a fresh parse.
     *
     * @throws OpenApiException when the file cannot be read or the spec fails validation.
     */
    public function parse(string $path): NormalizedSpec
    {
        if (! is_file($path)) {
            throw new OpenApiException("OpenAPI spec not found at: {$path}");
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
            throw new OpenApiException(
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
            Coerce::string($data['openapi'] ?? ''),
            Coerce::assoc($data['info'] ?? []),
            array_values(Coerce::listOfAssoc($data['servers'] ?? [])),
            array_values(Coerce::listOfAssoc($data['tags'] ?? [])),
            $this->operations(Coerce::assoc($data['paths'] ?? [])),
            $schemaNodes,
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
                    strtoupper($method),
                    (string) $path,
                    Coerce::nullableString($operation['operationId'] ?? null),
                    Coerce::nullableString($operation['summary'] ?? null),
                    Coerce::nullableString($operation['description'] ?? null),
                    array_values(Coerce::stringList($operation['tags'] ?? [])),
                    array_values(Coerce::listOfAssoc($operation['parameters'] ?? [])),
                    Coerce::assoc($operation['requestBody'] ?? []),
                    Coerce::assoc($operation['responses'] ?? []),
                    Coerce::bool($operation['deprecated'] ?? false),
                );
            }
        }

        return $operations;
    }

    /**
     * Recursively cast the cebe serialisable structure (nested stdClass/array)
     * into plain PHP arrays.
     * @param mixed $value
     * @return mixed
     */
    private function toArray($value)
    {
        if ($value instanceof \stdClass) {
            $value = (array) $value;
        }

        if (is_array($value)) {
            return array_map(function ($item) {
                return $this->toArray($item);
            }, $value);
        }

        return $value;
    }
}

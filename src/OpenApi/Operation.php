<?php

declare(strict_types=1);

namespace Laradocs\OpenApi;

use Illuminate\Contracts\Support\Arrayable;

/**
 * A single OpenAPI operation (one HTTP method on one path), flattened out of
 * the spec's nested paths object into a self-contained value object.
 *
 * Every property is a scalar or a plain array, so an Operation survives a
 * round-trip through a cache store with `cache.serializable_classes => false`.
 *
 * @implements Arrayable<string, mixed>
 */
final class Operation implements Arrayable
{
    /**
     * @param  string  $method  Upper-cased HTTP verb (GET, POST, …).
     * @param  array<int, string>  $tags
     * @param  array<int, array<string, mixed>>  $parameters
     * @param  array<string, mixed>  $requestBody
     * @param  array<string, mixed>  $responses
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly ?string $operationId = null,
        public readonly ?string $summary = null,
        public readonly ?string $description = null,
        public readonly array $tags = [],
        public readonly array $parameters = [],
        public readonly array $requestBody = [],
        public readonly array $responses = [],
        public readonly bool $deprecated = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            method: Coerce::string($data['method'] ?? ''),
            path: Coerce::string($data['path'] ?? ''),
            operationId: Coerce::nullableString($data['operationId'] ?? null),
            summary: Coerce::nullableString($data['summary'] ?? null),
            description: Coerce::nullableString($data['description'] ?? null),
            tags: array_values(Coerce::stringList($data['tags'] ?? [])),
            parameters: Coerce::listOfAssoc($data['parameters'] ?? []),
            requestBody: Coerce::assoc($data['requestBody'] ?? []),
            responses: Coerce::assoc($data['responses'] ?? []),
            deprecated: Coerce::bool($data['deprecated'] ?? false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'path' => $this->path,
            'operationId' => $this->operationId,
            'summary' => $this->summary,
            'description' => $this->description,
            'tags' => $this->tags,
            'parameters' => $this->parameters,
            'requestBody' => $this->requestBody,
            'responses' => $this->responses,
            'deprecated' => $this->deprecated,
        ];
    }
}

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
     * @var string
     * @readonly
     */
    public $method;
    /**
     * @readonly
     * @var string
     */
    public $path;
    /**
     * @readonly
     * @var string|null
     */
    public $operationId;
    /**
     * @readonly
     * @var string|null
     */
    public $summary;
    /**
     * @readonly
     * @var string|null
     */
    public $description;
    /**
     * @var array<int, string>
     * @readonly
     */
    public $tags = [];
    /**
     * @var array<int, array<string, mixed>>
     * @readonly
     */
    public $parameters = [];
    /**
     * @var array<string, mixed>
     * @readonly
     */
    public $requestBody = [];
    /**
     * @var array<string, mixed>
     * @readonly
     */
    public $responses = [];
    /**
     * @readonly
     * @var bool
     */
    public $deprecated = false;
    /**
     * @param  string  $method  Upper-cased HTTP verb (GET, POST, …).
     * @param  array<int, string>  $tags
     * @param  array<int, array<string, mixed>>  $parameters
     * @param  array<string, mixed>  $requestBody
     * @param  array<string, mixed>  $responses
     */
    public function __construct(string $method, string $path, ?string $operationId = null, ?string $summary = null, ?string $description = null, array $tags = [], array $parameters = [], array $requestBody = [], array $responses = [], bool $deprecated = false)
    {
        $this->method = $method;
        $this->path = $path;
        $this->operationId = $operationId;
        $this->summary = $summary;
        $this->description = $description;
        $this->tags = $tags;
        $this->parameters = $parameters;
        $this->requestBody = $requestBody;
        $this->responses = $responses;
        $this->deprecated = $deprecated;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Coerce::string($data['method'] ?? ''),
            Coerce::string($data['path'] ?? ''),
            Coerce::nullableString($data['operationId'] ?? null),
            Coerce::nullableString($data['summary'] ?? null),
            Coerce::nullableString($data['description'] ?? null),
            array_values(Coerce::stringList($data['tags'] ?? [])),
            Coerce::listOfAssoc($data['parameters'] ?? []),
            Coerce::assoc($data['requestBody'] ?? []),
            Coerce::assoc($data['responses'] ?? []),
            Coerce::bool($data['deprecated'] ?? false),
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

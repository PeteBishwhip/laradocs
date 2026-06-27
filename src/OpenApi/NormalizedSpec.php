<?php

declare(strict_types=1);

namespace Laradocs\OpenApi;

use Illuminate\Contracts\Support\Arrayable;

/**
 * A parsed OpenAPI document reduced to plain, cache-safe value objects.
 *
 * The spec is deliberately decoupled from the cebe library: every field here is
 * a scalar, a plain array, or one of this package's own value objects
 * (Operation, SchemaNode). No `cebe\openapi\*` instance ever leaks in, so a
 * NormalizedSpec round-trips cleanly through a cache store configured with
 * `cache.serializable_classes => false`. {@see toArray()} / {@see fromArray()}
 * provide a fully array-based representation for that purpose.
 *
 * @implements Arrayable<string, mixed>
 */
final class NormalizedSpec implements Arrayable
{
    /**
     * @param  array<string, mixed>  $info
     * @param  array<int, array<string, mixed>>  $servers
     * @param  array<int, array<string, mixed>>  $tags
     * @param  array<int, Operation>  $operations
     * @param  array<string, SchemaNode>  $schemas
     */
    public function __construct(
        public readonly string $openApiVersion,
        public readonly array $info,
        public readonly array $servers,
        public readonly array $tags,
        public readonly array $operations,
        public readonly array $schemas,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function info(): array
    {
        return $this->info;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function servers(): array
    {
        return $this->servers;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tags(): array
    {
        return $this->tags;
    }

    /**
     * @return array<int, Operation>
     */
    public function operations(): array
    {
        return $this->operations;
    }

    /**
     * @return array<string, SchemaNode>
     */
    public function schemas(): array
    {
        return $this->schemas;
    }

    /**
     * Rebuild a spec from its plain-array form (the shape stored in the cache).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $schemaNodes = [];

        foreach (Coerce::assoc($data['schemas'] ?? []) as $name => $schema) {
            $schemaNodes[(string) $name] = SchemaNode::fromArray(Coerce::assoc($schema));
        }

        return new self(
            openApiVersion: Coerce::string($data['openapi'] ?? ''),
            info: Coerce::assoc($data['info'] ?? []),
            servers: array_values(Coerce::listOfAssoc($data['servers'] ?? [])),
            tags: array_values(Coerce::listOfAssoc($data['tags'] ?? [])),
            operations: array_values(array_map(
                static fn (array $operation): Operation => Operation::fromArray($operation),
                Coerce::listOfAssoc($data['operations'] ?? []),
            )),
            schemas: $schemaNodes,
        );
    }

    /**
     * Flatten the spec to a nested array of scalars/arrays only — the form that
     * is safe to persist in any cache store.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'openapi' => $this->openApiVersion,
            'info' => $this->info,
            'servers' => $this->servers,
            'tags' => $this->tags,
            'operations' => array_map(
                static fn (Operation $operation): array => $operation->toArray(),
                $this->operations,
            ),
            'schemas' => array_map(
                static fn (SchemaNode $schema): array => $schema->toArray(),
                $this->schemas,
            ),
        ];
    }
}

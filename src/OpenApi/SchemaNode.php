<?php

declare(strict_types=1);

namespace Laradocs\OpenApi;

use Illuminate\Contracts\Support\Arrayable;

/**
 * A named entry from the spec's `components/schemas`, paired with its plain
 * array definition. Cyclic `$ref`s inside the definition are preserved as
 * `['$ref' => '#/components/schemas/…']` arrays rather than being expanded,
 * so the node is always a finite, serialisable structure.
 *
 * @implements Arrayable<string, mixed>
 */
final class SchemaNode implements Arrayable
{
    /**
     * @readonly
     * @var string
     */
    public $name;
    /**
     * @var array<string, mixed>
     * @readonly
     */
    public $definition;
    /**
     * @param  array<string, mixed>  $definition
     */
    public function __construct(string $name, array $definition)
    {
        $this->name = $name;
        $this->definition = $definition;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Coerce::string($data['name'] ?? ''),
            Coerce::assoc($data['definition'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'definition' => $this->definition,
        ];
    }
}

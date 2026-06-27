<?php

declare(strict_types=1);

namespace Laradocs\OpenApi;

/**
 * Expands an OpenAPI schema definition into a finite, fully-resolved tree of
 * plain arrays that downstream renderers can turn into HTML.
 *
 * The walker resolves `$ref`s against the spec's `components/schemas`, composes
 * `allOf`/`oneOf`/`anyOf`, surfaces `enum` values and nullability, and recurses
 * into nested objects and arrays. It operates purely on the plain-array data of
 * a {@see NormalizedSpec} (US-001) — no cebe object ever participates.
 *
 * Self-referential schemas are made finite by two independent guards:
 *  - a visited-set of `$ref` names on the current path, so a cycle resolves to a
 *    `['circular' => true]` marker instead of recursing forever; and
 *  - a hard depth cap, so even a (malformed) inline structure without `$ref`s
 *    terminates with a `['truncated' => true]` marker.
 */
final class SchemaRenderer
{
    private const DEFAULT_MAX_DEPTH = 20;

    /**
     * Named component schema definitions, keyed by component name.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $schemas;

    public function __construct(
        NormalizedSpec $spec,
        private readonly int $maxDepth = self::DEFAULT_MAX_DEPTH,
    ) {
        $schemas = [];

        foreach ($spec->schemas() as $name => $node) {
            $schemas[(string) $name] = $node->definition;
        }

        $this->schemas = $schemas;
    }

    /**
     * Expand a single schema definition into a finite, resolved node tree.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public function render(array $schema): array
    {
        return $this->walk($schema, 0, []);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<int, string>  $visited  `$ref` names on the current path.
     * @return array<string, mixed>
     */
    private function walk(array $schema, int $depth, array $visited): array
    {
        if ($depth >= $this->maxDepth) {
            return ['type' => 'object', 'nullable' => false, 'truncated' => true];
        }

        if (isset($schema['$ref']) && is_scalar($schema['$ref'])) {
            return $this->resolveRef((string) $schema['$ref'], $depth, $visited);
        }

        $keyword = $this->compositionKeyword($schema);

        return $keyword !== null
            ? $this->compose($keyword, $schema, $depth, $visited)
            : $this->walkPlain($schema, $depth, $visited);
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function compositionKeyword(array $schema): ?string
    {
        foreach (['allOf', 'oneOf', 'anyOf'] as $keyword) {
            if (isset($schema[$keyword]) && is_array($schema[$keyword])) {
                return $keyword;
            }
        }

        return null;
    }

    /**
     * Resolve a `$ref`, guarding against cycles via the visited-set.
     *
     * @param  array<int, string>  $visited
     * @return array<string, mixed>
     */
    private function resolveRef(string $ref, int $depth, array $visited): array
    {
        $name = $this->refName($ref);

        if ($name === '' || ! isset($this->schemas[$name])) {
            return ['type' => 'mixed', 'nullable' => false, 'ref' => $name === '' ? $ref : $name, 'unresolved' => true];
        }

        // A ref back to a name already on the path is a cycle: stop and mark it.
        if (in_array($name, $visited, true)) {
            return ['type' => 'object', 'nullable' => false, 'ref' => $name, 'circular' => true];
        }

        $resolved = $this->walk($this->schemas[$name], $depth + 1, [...$visited, $name]);
        $resolved['ref'] = $name;

        return $resolved;
    }

    /**
     * Compose an `allOf`/`oneOf`/`anyOf` schema.
     *
     * `allOf` is merged into a single object node (the most "expanded inline"
     * representation); `oneOf`/`anyOf` are kept as a list of resolved variants.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<int, string>  $visited
     * @return array<string, mixed>
     */
    private function compose(string $keyword, array $schema, int $depth, array $visited): array
    {
        $subschemas = Coerce::listOfAssoc($schema[$keyword]);

        // The schema's own (non-composition) fields, e.g. sibling properties.
        $own = $schema;
        unset($own['allOf'], $own['oneOf'], $own['anyOf']);
        $node = $this->walkPlain($own, $depth, $visited);

        if ($keyword === 'allOf') {
            $node['type'] = 'object';

            foreach ($subschemas as $sub) {
                $node = $this->mergeObjectNodes($node, $this->walk($sub, $depth + 1, $visited));
            }

            return $node;
        }

        $node[$keyword] = array_map(
            fn (array $sub): array => $this->walk($sub, $depth + 1, $visited),
            $subschemas,
        );

        return $node;
    }

    /**
     * Walk a plain (non-ref, non-composition) schema: type, nullability, enum,
     * nested object properties, and array items.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<int, string>  $visited
     * @return array<string, mixed>
     */
    private function walkPlain(array $schema, int $depth, array $visited): array
    {
        $node = $this->baseNode($schema);

        if (isset($schema['enum']) && is_array($schema['enum'])) {
            $node['enum'] = array_values($schema['enum']);
        }

        $required = Coerce::stringList($schema['required'] ?? []);

        if (isset($schema['properties']) && is_array($schema['properties'])) {
            $properties = [];

            foreach (Coerce::assoc($schema['properties']) as $name => $propSchema) {
                $properties[(string) $name] = [
                    'required' => in_array((string) $name, $required, true),
                    'schema' => $this->walk(Coerce::assoc($propSchema), $depth + 1, $visited),
                ];
            }

            if ($properties !== []) {
                $node['properties'] = $properties;

                if ($node['type'] === 'mixed') {
                    $node['type'] = 'object';
                }
            }
        }

        if (isset($schema['items']) && is_array($schema['items'])) {
            $node['items'] = $this->walk(Coerce::assoc($schema['items']), $depth + 1, $visited);

            if ($node['type'] === 'mixed') {
                $node['type'] = 'array';
            }
        }

        return $node;
    }

    /**
     * Build the scalar fields shared by every node: type, nullability, format,
     * and description.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function baseNode(array $schema): array
    {
        [$type, $nullableFromType] = $this->normalizeType($schema['type'] ?? null);

        $node = [
            'type' => $type,
            'nullable' => $nullableFromType || (($schema['nullable'] ?? false) === true),
        ];

        if (isset($schema['format']) && is_scalar($schema['format'])) {
            $node['format'] = (string) $schema['format'];
        }

        if (isset($schema['description']) && is_scalar($schema['description'])) {
            $node['description'] = (string) $schema['description'];
        }

        return $node;
    }

    /**
     * Merge a resolved subschema node into an accumulating object node, combining
     * properties, required flags (carried on each property), and nullability.
     *
     * @param  array<string, mixed>  $into
     * @param  array<string, mixed>  $from
     * @return array<string, mixed>
     */
    private function mergeObjectNodes(array $into, array $from): array
    {
        $properties = Coerce::assoc($into['properties'] ?? []);

        foreach (Coerce::assoc($from['properties'] ?? []) as $name => $definition) {
            $properties[(string) $name] = $definition;
        }

        if ($properties !== []) {
            $into['properties'] = $properties;
        }

        $into['nullable'] = ($into['nullable'] ?? false) || ($from['nullable'] ?? false);

        if (empty($into['description']) && ! empty($from['description'])) {
            $into['description'] = $from['description'];
        }

        return $into;
    }

    /**
     * Reduce an OpenAPI `type` (a string in 3.0, possibly a list including
     * `"null"` in 3.1) to a primary type plus whether `null` was a member.
     *
     * @return array{0: string, 1: bool}
     */
    private function normalizeType(mixed $type): array
    {
        if (is_array($type)) {
            $types = Coerce::stringList($type);
            $nullable = in_array('null', $types, true);
            $types = array_values(array_filter($types, static fn (string $t): bool => $t !== 'null'));

            return [$types[0] ?? 'mixed', $nullable];
        }

        if (is_scalar($type)) {
            return [(string) $type, false];
        }

        return ['mixed', false];
    }

    /**
     * Extract the component name from a local `$ref` (the segment after the last
     * slash), e.g. `#/components/schemas/Pet` → `Pet`.
     */
    private function refName(string $ref): string
    {
        $pos = strrpos($ref, '/');

        return $pos === false ? '' : substr($ref, $pos + 1);
    }
}

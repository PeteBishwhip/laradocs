<?php

declare(strict_types=1);

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Laradocs\OpenApi\NormalizedSpec;
use Laradocs\OpenApi\OpenApiParser;
use Laradocs\OpenApi\SchemaNode;
use Laradocs\OpenApi\SchemaRenderer;

/**
 * Build a NormalizedSpec carrying only the given component schema definitions.
 *
 * @param  array<string, array<string, mixed>>  $schemas
 */
function specWithSchemas(array $schemas): NormalizedSpec
{
    $nodes = [];

    foreach ($schemas as $name => $definition) {
        $nodes[$name] = new SchemaNode($name, $definition);
    }

    return new NormalizedSpec(
        openApiVersion: '3.0.3',
        info: [],
        servers: [],
        tags: [],
        operations: [],
        schemas: $nodes,
    );
}

$fixtures = dirname(__DIR__) . '/Fixtures/openapi';

it('resolves a $ref against the component schemas', function () {
    $spec = specWithSchemas([
        'Pet' => [
            'type' => 'object',
            'required' => ['id'],
            'properties' => [
                'id' => ['type' => 'integer', 'format' => 'int64'],
                'name' => ['type' => 'string'],
            ],
        ],
    ]);

    $node = (new SchemaRenderer($spec))->render(['$ref' => '#/components/schemas/Pet']);

    expect($node['ref'])->toBe('Pet')
        ->and($node['type'])->toBe('object')
        ->and($node['properties'])->toHaveKeys(['id', 'name'])
        ->and($node['properties']['id']['required'])->toBeTrue()
        ->and($node['properties']['id']['schema']['type'])->toBe('integer')
        ->and($node['properties']['id']['schema']['format'])->toBe('int64')
        ->and($node['properties']['name']['required'])->toBeFalse();
});

it('marks an unresolvable $ref instead of failing', function () {
    $node = (new SchemaRenderer(specWithSchemas([])))
        ->render(['$ref' => '#/components/schemas/Missing']);

    expect($node['unresolved'])->toBeTrue()
        ->and($node['ref'])->toBe('Missing');
});

it('merges allOf subschemas into a single object', function () {
    $spec = specWithSchemas([
        'Base' => [
            'type' => 'object',
            'required' => ['id'],
            'properties' => ['id' => ['type' => 'integer']],
        ],
    ]);

    $node = (new SchemaRenderer($spec))->render([
        'allOf' => [
            ['$ref' => '#/components/schemas/Base'],
            [
                'type' => 'object',
                'required' => ['name'],
                'properties' => ['name' => ['type' => 'string']],
            ],
        ],
    ]);

    expect($node['type'])->toBe('object')
        ->and($node['properties'])->toHaveKeys(['id', 'name'])
        ->and($node['properties']['id']['required'])->toBeTrue()
        ->and($node['properties']['id']['schema']['type'])->toBe('integer')
        ->and($node['properties']['name']['required'])->toBeTrue()
        ->and($node['properties']['name']['schema']['type'])->toBe('string');
});

it('keeps oneOf and anyOf variants as resolved alternatives', function () {
    $spec = specWithSchemas([
        'Cat' => ['type' => 'object', 'properties' => ['meow' => ['type' => 'boolean']]],
        'Dog' => ['type' => 'object', 'properties' => ['bark' => ['type' => 'boolean']]],
    ]);

    $renderer = new SchemaRenderer($spec);

    $oneOf = $renderer->render([
        'oneOf' => [
            ['$ref' => '#/components/schemas/Cat'],
            ['$ref' => '#/components/schemas/Dog'],
        ],
    ]);

    expect($oneOf['oneOf'])->toHaveCount(2)
        ->and($oneOf['oneOf'][0]['ref'])->toBe('Cat')
        ->and($oneOf['oneOf'][0]['properties'])->toHaveKey('meow')
        ->and($oneOf['oneOf'][1]['ref'])->toBe('Dog');

    $anyOf = $renderer->render([
        'anyOf' => [
            ['type' => 'string'],
            ['type' => 'integer'],
        ],
    ]);

    expect($anyOf['anyOf'])->toHaveCount(2)
        ->and($anyOf['anyOf'][0]['type'])->toBe('string')
        ->and($anyOf['anyOf'][1]['type'])->toBe('integer');
});

it('represents enum values', function () {
    $node = (new SchemaRenderer(specWithSchemas([])))->render([
        'type' => 'string',
        'enum' => ['available', 'pending', 'sold'],
    ]);

    expect($node['type'])->toBe('string')
        ->and($node['enum'])->toBe(['available', 'pending', 'sold']);
});

it('represents 3.0-style nullable', function () {
    $node = (new SchemaRenderer(specWithSchemas([])))->render([
        'type' => 'string',
        'nullable' => true,
    ]);

    expect($node['type'])->toBe('string')
        ->and($node['nullable'])->toBeTrue();
});

it('represents 3.1-style nullable type lists', function () {
    $node = (new SchemaRenderer(specWithSchemas([])))->render([
        'type' => ['string', 'null'],
    ]);

    expect($node['type'])->toBe('string')
        ->and($node['nullable'])->toBeTrue();
});

it('defaults to non-nullable', function () {
    $node = (new SchemaRenderer(specWithSchemas([])))->render(['type' => 'string']);

    expect($node['nullable'])->toBeFalse();
});

it('walks nested objects and arrays recursively', function () {
    $node = (new SchemaRenderer(specWithSchemas([])))->render([
        'type' => 'object',
        'properties' => [
            'tags' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'label' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
    ]);

    $items = $node['properties']['tags']['schema']['items'];

    expect($node['properties']['tags']['schema']['type'])->toBe('array')
        ->and($items['type'])->toBe('object')
        ->and($items['properties']['label']['schema']['type'])->toBe('string');
});

it('renders a self-referential ($ref cycle) schema finitely', function () {
    $spec = specWithSchemas([
        'Node' => [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'parent' => ['$ref' => '#/components/schemas/Node'],
                'children' => [
                    'type' => 'array',
                    'items' => ['$ref' => '#/components/schemas/Node'],
                ],
            ],
        ],
    ]);

    $node = (new SchemaRenderer($spec))->render(['$ref' => '#/components/schemas/Node']);

    // The recursive references terminate as finite circular markers.
    expect($node['ref'])->toBe('Node')
        ->and($node['properties']['parent']['schema']['circular'])->toBeTrue()
        ->and($node['properties']['parent']['schema']['ref'])->toBe('Node')
        ->and($node['properties']['children']['schema']['items']['circular'])->toBeTrue();

    // A finite structure serialises without exhausting memory/stack.
    expect(strlen(serialize($node)))->toBeGreaterThan(0);
});

it('caps depth for a deeply nested inline structure', function () {
    // Build a 60-deep nested-object chain with no $ref to exercise the depth cap.
    $schema = ['type' => 'string'];

    for ($i = 0; $i < 60; $i++) {
        $schema = [
            'type' => 'object',
            'properties' => ['child' => $schema],
        ];
    }

    $renderer = new SchemaRenderer(specWithSchemas([]), maxDepth: 10);

    // Descend the rendered tree; somewhere within the cap it must be truncated.
    $node = $renderer->render($schema);
    $truncated = false;

    for ($i = 0; $i < 100; $i++) {
        if (($node['truncated'] ?? false) === true) {
            $truncated = true;
            break;
        }

        if (! isset($node['properties']['child']['schema'])) {
            break;
        }

        $node = $node['properties']['child']['schema'];
    }

    expect($truncated)->toBeTrue();
});

it('renders the cyclic fixture spec finitely end-to-end', function () use ($fixtures) {
    /** @var Repository $store */
    $store = Cache::store();
    $spec = (new OpenApiParser($store))->parse($fixtures . '/cyclic.yaml');

    $renderer = new SchemaRenderer($spec);
    $node = $renderer->render(['$ref' => '#/components/schemas/Node']);

    expect($node['ref'])->toBe('Node')
        ->and($node['type'])->toBe('object')
        ->and($node['properties']['parent']['schema']['circular'])->toBeTrue()
        ->and($node['properties']['children']['schema']['type'])->toBe('array')
        ->and($node['properties']['children']['schema']['items']['circular'])->toBeTrue();
});

it('infers object/array type from properties/items and carries descriptions', function () {
    $renderer = new SchemaRenderer(specWithSchemas([]));

    // No explicit type, but properties present -> inferred object.
    $object = $renderer->render(['properties' => ['a' => ['type' => 'string']]]);
    expect($object['type'])->toBe('object');

    // No explicit type, but items present -> inferred array.
    $array = $renderer->render(['items' => ['type' => 'string']]);
    expect($array['type'])->toBe('array');

    // A node-level description is surfaced.
    $described = $renderer->render(['type' => 'string', 'description' => 'A field.']);
    expect($described['description'])->toBe('A field.');
});

it('carries a description up from an allOf member into the merged node', function () {
    $node = (new SchemaRenderer(specWithSchemas([])))->render([
        'allOf' => [
            [
                'type' => 'object',
                'description' => 'Merged in.',
                'properties' => ['a' => ['type' => 'string']],
            ],
        ],
    ]);

    expect($node['type'])->toBe('object')
        ->and($node['description'])->toBe('Merged in.');
});

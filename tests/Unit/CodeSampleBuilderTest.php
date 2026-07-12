<?php

declare(strict_types=1);

use Laradocs\OpenApi\CodeSampleBuilder;

$builder = function (): CodeSampleBuilder {
    return new CodeSampleBuilder;
};

it('builds a snippet for every supported language', function () use ($builder) {
    $samples = $builder()->forOperation('GET', 'https://api.test/v1/widgets', null);

    expect(array_keys($samples))->toBe(['cURL', 'PHP', 'JavaScript', 'Python', 'Ruby']);
});

it('emits a body-less request for GET even when a schema is supplied', function () use ($builder) {
    $schema = ['type' => 'object', 'properties' => ['name' => ['schema' => ['type' => 'string']]]];

    $samples = $builder()->forOperation('GET', 'https://api.test/v1/widgets', $schema);

    expect($samples['cURL'])
        ->toContain('curl -X GET "https://api.test/v1/widgets"')
        ->not->toContain('-d ')
        ->not->toContain('Content-Type');
    expect($samples['PHP'])->toContain("->get('https://api.test/v1/widgets');");
    expect($samples['Python'])->toContain('requests.get(');
    expect($samples['Ruby'])->toContain('Net::HTTP::Get.new(uri)');
    expect($samples['JavaScript'])->toContain('method: "GET"');
});

it('renders the request body in each language for a write operation', function () use ($builder) {
    $schema = [
        'type' => 'object',
        'properties' => [
            'name' => ['required' => true, 'schema' => ['type' => 'string']],
            'count' => ['schema' => ['type' => 'integer']],
            'active' => ['schema' => ['type' => 'boolean']],
            'kind' => ['schema' => ['type' => 'string', 'enum' => ['a', 'b']]],
            'tags' => ['schema' => ['type' => 'array', 'items' => ['type' => 'string']]],
            'meta' => ['schema' => ['type' => 'object', 'properties' => ['id' => ['schema' => ['type' => 'string', 'format' => 'uuid']]]]],
        ],
    ];

    $samples = $builder()->forOperation('POST', 'https://api.test/v1/widgets', $schema);

    // cURL / JSON style.
    expect($samples['cURL'])
        ->toContain('-X POST')
        ->toContain('-H "Content-Type: application/json"')
        ->toContain('"name": "string"')
        ->toContain('"count": 0')
        ->toContain('"active": true')
        ->toContain('"kind": "a"')
        ->toContain('"tags": [')
        ->toContain('"id": "00000000-0000-0000-0000-000000000000"');

    // PHP array literal.
    expect($samples['PHP'])
        ->toContain('Http::withToken(')
        ->toContain("->post('https://api.test/v1/widgets', [")
        ->toContain("'name' => 'string'")
        ->toContain("'active' => true");

    // Python dict.
    expect($samples['Python'])
        ->toContain('json=')
        ->toContain('"active": True');

    // Ruby hash + to_json.
    expect($samples['Ruby'])
        ->toContain('.to_json')
        ->toContain('"active" => true');

    // JavaScript embeds JSON.
    expect($samples['JavaScript'])->toContain('JSON.stringify(');
});

it('synthesises example strings from common formats', function () use ($builder) {
    $schema = [
        'type' => 'object',
        'properties' => [
            'at' => ['schema' => ['type' => 'string', 'format' => 'date-time']],
            'on' => ['schema' => ['type' => 'string', 'format' => 'date']],
            'email' => ['schema' => ['type' => 'string', 'format' => 'email']],
            'site' => ['schema' => ['type' => 'string', 'format' => 'uri']],
        ],
    ];

    $json = $builder()->responseJson($schema);

    expect($json)
        ->toContain('"at": "2024-01-01T00:00:00Z"')
        ->toContain('"on": "2024-01-01"')
        ->toContain('"email": "user@example.com"')
        ->toContain('"site": "https://example.com"');
});

it('resolves oneOf/anyOf to the first variant and enum to its first value', function () use ($builder) {
    $oneOf = ['oneOf' => [['type' => 'string', 'enum' => ['first', 'second']], ['type' => 'integer']]];

    $json = $builder()->responseJson(['type' => 'object', 'properties' => ['choice' => ['schema' => $oneOf]]]);

    expect($json)->toContain('"choice": "first"');
});

it('caps recursion depth so cyclic-looking schemas terminate', function () use ($builder) {
    // Nest objects deeper than the depth guard; the innermost becomes null.
    $node = ['type' => 'string'];
    for ($i = 0; $i < 8; $i++) {
        $node = ['type' => 'object', 'properties' => ['child' => ['schema' => $node]]];
    }

    $json = $builder()->responseJson($node);

    expect($json)->toContain('null');
});

it('returns null response JSON when there is no renderable body', function () use ($builder) {
    expect($builder()->responseJson(null))->toBeNull();
    // An object with no properties synthesises an empty example → nothing to show.
    expect($builder()->responseJson(['type' => 'object', 'properties' => []]))->toBeNull();
});

it('renders a bodyless object as an empty literal', function () use ($builder) {
    // A POST whose body schema declares no properties still emits a body — an
    // empty object literal — exercising the empty-map render branch.
    $samples = $builder()->forOperation('POST', 'https://api.test/v1/x', ['type' => 'object', 'properties' => []]);

    expect($samples['cURL'])->toContain("-d '{}'");
    expect($samples['PHP'])->toContain("->post('https://api.test/v1/x', []);");
});

it('escapes quotes in string values per language', function () use ($builder) {
    $schema = ['type' => 'object', 'properties' => [
        'note' => ['schema' => ['type' => 'string', 'enum' => ["O'Brien \"x\""]]],
    ]];

    $samples = $builder()->forOperation('POST', 'https://api.test/v1/x', $schema);

    expect($samples['PHP'])->toContain("O\\'Brien");        // single-quote escaped for PHP
    expect($samples['Python'])->toContain('\\"x\\"');       // double-quote escaped for Python
});

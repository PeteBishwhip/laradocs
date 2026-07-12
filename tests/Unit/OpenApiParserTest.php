<?php

declare(strict_types=1);

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Laradocs\OpenApi\NormalizedSpec;
use Laradocs\OpenApi\OpenApiParser;
use Laradocs\OpenApi\Operation;
use Laradocs\OpenApi\SchemaNode;
use Laradocs\Support\CacheKey;

/**
 * Recursively assert a value contains only scalars and plain arrays — never an
 * object. This is what makes the structure safe under
 * `cache.serializable_classes => false`.
 * @param mixed $value
 */
function assertNoObjects($value): void
{
    expect(is_object($value))->toBeFalse();

    if (is_array($value)) {
        foreach ($value as $item) {
            assertNoObjects($item);
        }
    }
}

function makeParser(bool $cacheEnabled = true): OpenApiParser
{
    /** @var Repository $store */
    $store = Cache::store();

    return new OpenApiParser($store, $cacheEnabled);
}

$fixtures = dirname(__DIR__) . '/Fixtures/openapi';

it('parses a 3.0 spec into a normalized spec', function () use ($fixtures) {
    $spec = makeParser()->parse($fixtures . '/petstore-3.0.yaml');

    expect($spec)->toBeInstanceOf(NormalizedSpec::class)
        ->and($spec->openApiVersion)->toBe('3.0.3')
        ->and($spec->info()['title'])->toBe('Petstore 3.0')
        ->and($spec->info()['version'])->toBe('1.0.0')
        ->and($spec->servers())->toHaveCount(1)
        ->and($spec->servers()[0]['url'])->toBe('https://api.example.com/v1')
        ->and($spec->tags())->toHaveCount(1)
        ->and($spec->tags()[0]['name'])->toBe('pets')
        ->and($spec->operations())->toHaveCount(2)
        ->and($spec->schemas())->toHaveKey('Pet');

    $operationIds = array_map(function (Operation $op): ?string {
        return $op->operationId;
    }, $spec->operations());
    expect($operationIds)->toContain('listPets')->toContain('createPet');

    $list = collect($spec->operations())->firstWhere('operationId', 'listPets');
    expect($list)->toBeInstanceOf(Operation::class)
        ->and($list->method)->toBe('GET')
        ->and($list->path)->toBe('/pets')
        ->and($list->tags)->toBe(['pets'])
        ->and($list->parameters)->toHaveCount(1);

    expect($spec->schemas()['Pet'])->toBeInstanceOf(SchemaNode::class)
        ->and($spec->schemas()['Pet']->definition['type'])->toBe('object');
});

it('parses a 3.1 spec into a normalized spec', function () use ($fixtures) {
    $spec = makeParser()->parse($fixtures . '/petstore-3.1.json');

    expect($spec->openApiVersion)->toBe('3.1.0')
        ->and($spec->info()['title'])->toBe('Petstore 3.1')
        ->and($spec->operations())->toHaveCount(2)
        ->and($spec->schemas())->toHaveKey('Pet');

    $delete = collect($spec->operations())->firstWhere('operationId', 'deletePet');
    expect($delete->method)->toBe('DELETE')
        ->and($delete->path)->toBe('/pets/{petId}')
        ->and($delete->deprecated)->toBeTrue();
});

it('parses a spec with cyclic $refs without recursing forever', function () use ($fixtures) {
    $spec = makeParser()->parse($fixtures . '/cyclic.yaml');

    expect($spec->operations())->toHaveCount(1)
        ->and($spec->schemas())->toHaveKey('Node');

    // The recursive self-reference is preserved as a finite $ref marker rather
    // than being expanded into an infinitely deep structure.
    $node = $spec->schemas()['Node']->definition;
    expect($node['properties']['parent'])->toHaveKey('$ref');

    // Round-tripping proves the structure is finite and serialisable.
    expect(strlen(serialize($spec->toArray())))->toBeGreaterThan(0);
});

it('produces output containing only arrays and plain value objects', function () use ($fixtures) {
    $spec = makeParser()->parse($fixtures . '/petstore-3.0.yaml');

    // toArray() is pure scalars/arrays — the form persisted to the cache.
    assertNoObjects($spec->toArray());

    // No cebe\openapi instance leaks into the value objects.
    foreach ($spec->operations() as $operation) {
        assertNoObjects($operation->toArray());
    }

    foreach ($spec->schemas() as $schema) {
        assertNoObjects($schema->toArray());
    }
});

it('caches the normalized spec as a plain array keyed by path + mtime', function () use ($fixtures) {
    $path = $fixtures . '/petstore-3.0.yaml';
    $key = CacheKey::openApi($path, (int) filemtime($path));

    expect(Cache::store()->has($key))->toBeFalse();

    makeParser()->parse($path);

    // The cached payload is a plain array (safe with serializable_classes=false).
    $cached = Cache::store()->get($key);
    expect($cached)->toBeArray();
    assertNoObjects($cached);

    // A second parse reconstructs the same spec from the cache.
    $spec = makeParser()->parse($path);
    expect($spec)->toBeInstanceOf(NormalizedSpec::class)
        ->and($spec->info()['title'])->toBe('Petstore 3.0');
});

it('reparses when the spec mtime changes', function () use ($fixtures) {
    $path = sys_get_temp_dir() . '/laradocs-openapi-' . uniqid() . '.yaml';
    copy($fixtures . '/petstore-3.0.yaml', $path);

    try {
        $first = makeParser()->parse($path);
        expect($first->info()['title'])->toBe('Petstore 3.0');

        // Rewrite the file with different content and a newer mtime.
        $changed = str_replace('Petstore 3.0', 'Petstore Changed', file_get_contents($path));
        file_put_contents($path, $changed);
        touch($path, time() + 30);
        clearstatcache(true, $path);

        $second = makeParser()->parse($path);
        expect($second->info()['title'])->toBe('Petstore Changed');
    } finally {
        @unlink($path);
    }
});

it('throws when the spec file does not exist', function () {
    makeParser(false)->parse('/no/such/openapi.yaml');
})->throws(RuntimeException::class);

it('throws when the spec fails validation', function () use ($fixtures) {
    makeParser(false)->parse($fixtures . '/invalid.yaml');
})->throws(RuntimeException::class);

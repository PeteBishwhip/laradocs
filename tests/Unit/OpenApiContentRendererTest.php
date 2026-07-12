<?php

declare(strict_types=1);

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Laradocs\Contracts\DocumentParser;
use Laradocs\Documents\Document;
use Laradocs\Metadata\Metadata;
use Laradocs\OpenApi\OpenApiContentRenderer;
use Laradocs\OpenApi\OpenApiParser;
use Laradocs\Toc\TableOfContents;

$fixtures = dirname(__DIR__) . '/Fixtures/openapi';

function makeOpenApiRenderer(bool $renderMarkdown = true): OpenApiContentRenderer
{
    /** @var Repository $store */
    $store = Cache::store();

    return new OpenApiContentRenderer(
        new OpenApiParser($store, false),
        app(DocumentParser::class),
        $renderMarkdown,
    );
}

function makeOperationDocument(string $specPath, string $method, string $path, ?string $operationId = null): Document
{
    return new Document(
        $specPath . '#op@',
        $specPath . '#op@',
        'api/pets/op',
        Metadata::fromArray([
            'title' => 'An operation',
            'openapi' => [
                'type' => 'operation',
                'spec' => $specPath,
                'op' => ['method' => $method, 'path' => $path, 'operationId' => $operationId],
            ],
        ]),
        '',
    );
}

it('supports only documents carrying an openapi marker', function () {
    $renderer = makeOpenApiRenderer();

    $openapi = makeDocument('api/pets/listpets', [
        'openapi' => ['type' => 'operation', 'spec' => '/x.yaml', 'op' => []],
    ]);
    $plain = makeDocument('guide/intro', ['title' => 'Intro']);

    expect($renderer->supports($openapi))->toBeTrue()
        ->and($renderer->supports($plain))->toBeFalse();
});

it('renders an operation page with method, path, parameters and response schema', function () use ($fixtures) {
    $spec = $fixtures . '/petstore-3.0.yaml';
    $document = makeOperationDocument($spec, 'GET', '/pets', 'listPets');

    $html = makeOpenApiRenderer()->render($document);

    expect($html)
        ->toContain('GET')
        ->toContain('/pets')
        ->toContain('Parameters')
        ->toContain('limit')          // the query parameter
        ->toContain('Responses')
        // The Pet $ref is inlined by the parser, so its properties surface in
        // the response schema rather than the component name.
        ->toContain('name')
        ->toContain('tag');
});

it('expands a request body $ref into properties', function () use ($fixtures) {
    $spec = $fixtures . '/petstore-3.0.yaml';
    $document = makeOperationDocument($spec, 'POST', '/pets', 'createPet');

    $html = makeOpenApiRenderer()->render($document);

    expect($html)
        ->toContain('Request Body')
        ->toContain('name')           // a property of the Pet schema
        ->toContain('tag');
});

it('emits no <h1> and yields TOC anchors from heading ids', function () use ($fixtures) {
    $spec = $fixtures . '/petstore-3.0.yaml';
    $document = makeOperationDocument($spec, 'GET', '/pets', 'listPets');

    $html = makeOpenApiRenderer()->render($document);

    expect($html)->not->toContain('<h1');

    $toc = TableOfContents::fromHtml($html);

    expect($toc->isEmpty())->toBeFalse();

    $ids = array_map(function ($heading): string {
        return $heading->id;
    }, $toc->headings);
    expect($ids)->toContain('parameters')
        ->toContain('responses');
});

it('runs descriptions through the markdown parser when enabled', function () use ($fixtures) {
    $spec = $fixtures . '/petstore-3.0.yaml';
    $document = makeOperationDocument($spec, 'GET', '/pets', 'listPets');

    // "Returns every pet in the store." is the operation description; markdown
    // rendering wraps it in a <p> via the DocumentParser.
    $html = makeOpenApiRenderer(true)->render($document);

    expect($html)->toContain('<p>Returns every pet in the store.</p>');
});

it('escapes descriptions as plain text when markdown rendering is off', function () use ($fixtures) {
    $spec = $fixtures . '/petstore-3.0.yaml';
    $document = makeOperationDocument($spec, 'GET', '/pets', 'listPets');

    $html = makeOpenApiRenderer(false)->render($document);

    expect($html)->toContain('Returns every pet in the store.');
});

it('renders a deprecated operation marker', function () use ($fixtures) {
    $spec = $fixtures . '/petstore-3.1.json';
    $document = makeOperationDocument($spec, 'DELETE', '/pets/{petId}', 'deletePet');

    $html = makeOpenApiRenderer()->render($document);

    expect($html)->toContain('Deprecated');
});

it('renders the overview page with info, servers and operations grouped by tag', function () use ($fixtures) {
    $spec = $fixtures . '/petstore-3.0.yaml';
    $document = new Document(
        $spec . '#overview@',
        $spec . '#overview@',
        'api',
        Metadata::fromArray([
            'title' => 'API Reference',
            'openapi' => ['type' => 'overview', 'spec' => $spec],
        ]),
        '',
    );

    $html = makeOpenApiRenderer()->render($document);

    expect($html)
        ->not->toContain('<h1')
        ->toContain('https://api.example.com/v1') // base URL surfaced in the meta panel
        ->toContain('pets')           // the tag grouping the operations
        ->toContain('List all pets'); // an operation summary

    // The overview groups operations into a section per tag, each heading
    // anchored as tag-{slug} so the on-page TOC lists every resource.
    $toc = TableOfContents::fromHtml($html);
    $ids = array_map(function ($heading): string {
        return $heading->id;
    }, $toc->headings);
    expect($ids)->toContain('tag-pets');
});

it('renders an operation whose spec declares no servers', function () use ($fixtures) {
    // cyclic.yaml has no `servers` block, so the base URL falls back to an
    // empty string and the sample URL is just the operation path.
    $spec = $fixtures . '/cyclic.yaml';
    $document = makeOperationDocument($spec, 'GET', '/nodes', 'listNodes');

    $html = makeOpenApiRenderer()->render($document);

    expect($html)
        ->toContain('GET')
        ->toContain('/nodes')
        ->toContain('Responses');
});

it('returns empty string for a marker pointing at a missing spec', function () {
    $document = makeDocument('api/pets/x', [
        'openapi' => ['type' => 'operation', 'spec' => '/does/not/exist.yaml', 'op' => []],
    ]);

    expect(makeOpenApiRenderer()->render($document))->toBe('');
});

it('renders an empty string when the openapi marker is not an array', function () {
    $document = makeDocument('api/x', ['openapi' => 'not-an-array']);

    expect(makeOpenApiRenderer()->render($document))->toBe('');
});

it('renders an empty string when the operation cannot be located in the spec', function () use ($fixtures) {
    $document = makeOperationDocument($fixtures . '/petstore-3.0.yaml', 'GET', '/no-such-path');

    expect(makeOpenApiRenderer()->render($document))->toBe('');
});

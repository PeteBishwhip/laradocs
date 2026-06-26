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
        new OpenApiParser($store, cacheEnabled: false),
        app(DocumentParser::class),
        $renderMarkdown,
    );
}

function makeOperationDocument(string $specPath, string $method, string $path, ?string $operationId = null): Document
{
    return new Document(
        path: $specPath . '#op@',
        relativePath: $specPath . '#op@',
        slug: 'api/pets/op',
        metadata: Metadata::fromArray([
            'title' => 'An operation',
            'openapi' => [
                'type' => 'operation',
                'spec' => $specPath,
                'op' => ['method' => $method, 'path' => $path, 'operationId' => $operationId],
            ],
        ]),
        markdown: '',
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

    $ids = array_map(fn ($heading): string => $heading->id, $toc->headings);
    expect($ids)->toContain('parameters')
        ->toContain('responses');
});

it('runs descriptions through the markdown parser when enabled', function () use ($fixtures) {
    $spec = $fixtures . '/petstore-3.0.yaml';
    $document = makeOperationDocument($spec, 'GET', '/pets', 'listPets');

    // "Returns every pet in the store." is the operation description; markdown
    // rendering wraps it in a <p> via the DocumentParser.
    $html = makeOpenApiRenderer(renderMarkdown: true)->render($document);

    expect($html)->toContain('<p>Returns every pet in the store.</p>');
});

it('escapes descriptions as plain text when markdown rendering is off', function () use ($fixtures) {
    $spec = $fixtures . '/petstore-3.0.yaml';
    $document = makeOperationDocument($spec, 'GET', '/pets', 'listPets');

    $html = makeOpenApiRenderer(renderMarkdown: false)->render($document);

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
        path: $spec . '#overview@',
        relativePath: $spec . '#overview@',
        slug: 'api',
        metadata: Metadata::fromArray([
            'title' => 'API Reference',
            'openapi' => ['type' => 'overview', 'spec' => $spec],
        ]),
        markdown: '',
    );

    $html = makeOpenApiRenderer()->render($document);

    expect($html)
        ->not->toContain('<h1')
        ->toContain('Servers')
        ->toContain('https://api.example.com/v1')
        ->toContain('Operations')
        ->toContain('pets')           // the tag grouping the operations
        ->toContain('List all pets'); // an operation summary

    $toc = TableOfContents::fromHtml($html);
    $ids = array_map(fn ($heading): string => $heading->id, $toc->headings);
    expect($ids)->toContain('servers')->toContain('operations');
});

it('returns empty string for a marker pointing at a missing spec', function () {
    $document = makeDocument('api/pets/x', [
        'openapi' => ['type' => 'operation', 'spec' => '/does/not/exist.yaml', 'op' => []],
    ]);

    expect(makeOpenApiRenderer()->render($document))->toBe('');
});

<?php

declare(strict_types=1);

use Laradocs\OpenApi\Operation;
use Laradocs\OpenApi\OperationSlugger;

function op(array $data): Operation
{
    return Operation::fromArray($data);
}

it('prefers the summary for a clean, title-matching slug', function () {
    $slugs = OperationSlugger::map([
        op(['method' => 'GET', 'path' => '/x', 'summary' => 'List background processes', 'operationId' => 'orgs.servers.bg.index', 'tags' => ['Background Processes']]),
    ], 'api');

    expect($slugs)->toBe(['GET /x' => 'api/background-processes/list-background-processes']);
});

it('falls back to the operationId when there is no summary', function () {
    $slugs = OperationSlugger::map([
        op(['method' => 'GET', 'path' => '/x', 'operationId' => 'listWidgets', 'tags' => ['Widgets']]),
    ], 'api');

    expect($slugs['GET /x'])->toBe('api/widgets/listwidgets');
});

it('falls back to the method and path when there is no summary or operationId', function () {
    $slugs = OperationSlugger::map([
        op(['method' => 'GET', 'path' => '/things', 'tags' => ['Widgets']]),
    ], 'api');

    expect($slugs['GET /things'])->toBe('api/widgets/get-things');
});

it('falls back to a constant segment when nothing yields a slug', function () {
    // Empty method and path (and no summary/operationId) slug to nothing.
    $slugs = OperationSlugger::map([op(['method' => '', 'path' => ''])], 'api');

    expect($slugs[' '])->toBe('api/default/operation');
});

it('untagged operations fall under the default group', function () {
    $slugs = OperationSlugger::map([
        op(['method' => 'GET', 'path' => '/x', 'summary' => 'List all']),
    ], 'api');

    expect($slugs['GET /x'])->toBe('api/default/list-all');
});

it('appends a stable numeric suffix on a slug collision', function () {
    $slugs = OperationSlugger::map([
        op(['method' => 'GET', 'path' => '/a', 'summary' => 'Fetch order', 'tags' => ['Orders']]),
        op(['method' => 'GET', 'path' => '/b', 'summary' => 'Fetch order', 'tags' => ['Orders']]),
        op(['method' => 'GET', 'path' => '/c', 'summary' => 'Fetch order', 'tags' => ['Orders']]),
    ], 'api');

    expect(array_values($slugs))->toBe([
        'api/orders/fetch-order',
        'api/orders/fetch-order-2',
        'api/orders/fetch-order-3',
    ]);
});

it('honours a custom base slug', function () {
    $slugs = OperationSlugger::map([
        op(['method' => 'POST', 'path' => '/x', 'summary' => 'Create thing', 'tags' => ['Things']]),
    ], 'reference');

    expect($slugs['POST /x'])->toBe('reference/things/create-thing');
});

it('resolve prefers the canonical slug for a shared operation', function () {
    // The same operation (GET /x) with a translated summary must keep the
    // canonical (default-locale) slug, so a translation never moves the URL.
    $translated = [op(['method' => 'GET', 'path' => '/x', 'summary' => 'Alle Widgets auflisten', 'tags' => ['Widgets']])];
    $canonical = [op(['method' => 'GET', 'path' => '/x', 'summary' => 'List all widgets', 'tags' => ['Widgets']])];

    $slugs = OperationSlugger::resolve($translated, $canonical, 'api');

    expect($slugs['GET /x'])->toBe('api/widgets/list-all-widgets');
});

it('resolve falls back to a locale-only operation own slug', function () {
    // An operation the canonical spec does not describe keeps its own slug.
    $translated = [
        op(['method' => 'GET', 'path' => '/x', 'summary' => 'List all widgets', 'tags' => ['Widgets']]),
        op(['method' => 'GET', 'path' => '/y', 'summary' => 'Extra endpoint', 'tags' => ['Widgets']]),
    ];
    $canonical = [op(['method' => 'GET', 'path' => '/x', 'summary' => 'List all widgets', 'tags' => ['Widgets']])];

    $slugs = OperationSlugger::resolve($translated, $canonical, 'api');

    expect($slugs)->toBe([
        'GET /x' => 'api/widgets/list-all-widgets',
        'GET /y' => 'api/widgets/extra-endpoint',
    ]);
});

it('resolve keeps a locale-only slug distinct from a colliding canonical slug', function () {
    // GET /a is shared and claims `api/widgets/foo`. GET /b is locale-only and
    // its summary slugs to the same value; it must gain a suffix rather than
    // duplicate the canonical URL (which would shadow one of the two pages).
    $translated = [
        op(['method' => 'GET', 'path' => '/a', 'summary' => 'Foo (fr)', 'tags' => ['Widgets']]),
        op(['method' => 'GET', 'path' => '/b', 'summary' => 'Foo', 'tags' => ['Widgets']]),
    ];
    $canonical = [op(['method' => 'GET', 'path' => '/a', 'summary' => 'Foo', 'tags' => ['Widgets']])];

    $slugs = OperationSlugger::resolve($translated, $canonical, 'api');

    expect($slugs)->toBe([
        'GET /a' => 'api/widgets/foo',
        'GET /b' => 'api/widgets/foo-2',
    ]);
    expect(array_values($slugs))->toBe(array_unique(array_values($slugs)));
});

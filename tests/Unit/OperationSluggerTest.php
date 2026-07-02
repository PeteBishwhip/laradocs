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

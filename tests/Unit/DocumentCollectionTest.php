<?php

declare(strict_types=1);

use Laradocs\Documents\DocumentCollection;

function collection(): DocumentCollection
{
    return new DocumentCollection([
        makeDocument('b', ['title' => 'Beta', 'order' => 2, 'group' => 'G1', 'tags' => ['x']]),
        makeDocument('a', ['title' => 'Alpha', 'order' => 1, 'group' => 'G1']),
        makeDocument('c', ['title' => 'Gamma', 'order' => 1, 'hidden' => true, 'tags' => ['x', 'y']]),
    ]);
}

it('filters out hidden documents', function () {
    expect(collection()->visible()->pluck('slug')->all())->toBe(['b', 'a']);
});

it('orders by order then title', function () {
    expect(collection()->ordered()->pluck('slug')->all())->toBe(['a', 'c', 'b']);
});

it('buckets by group', function () {
    $groups = collection()->byGroup();

    expect($groups->get('G1'))->toHaveCount(2)
        ->and($groups->get(''))->toHaveCount(1);
});

it('filters by tag', function () {
    expect(collection()->byTag('y')->pluck('slug')->all())->toBe(['c'])
        ->and(collection()->byTag('x'))->toHaveCount(2);
});

it('finds by slug', function () {
    expect(collection()->findBySlug('a')?->title())->toBe('Alpha')
        ->and(collection()->findBySlug('missing'))->toBeNull();
});

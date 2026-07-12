<?php

declare(strict_types=1);

use Laradocs\Documents\DocumentCollection;
use Laradocs\Documents\DocumentTree;
use Laradocs\Support\Navigation;

function navTree(): array
{
    $docs = new DocumentCollection([
        makeDocument('guide', ['title' => 'Guide', 'order' => 1], '', 'guide/_index.md'),
        makeDocument('guide/intro', ['title' => 'Intro', 'order' => 1]),
        makeDocument('guide/advanced', ['title' => 'Advanced', 'order' => 2]),
        makeDocument('about', ['title' => 'About', 'order' => 2]),
    ]);

    return DocumentTree::fromDocuments($docs)->navigation();
}

it('flattens linkable nodes depth-first', function () {
    expect(collect(Navigation::flatten(navTree()))->pluck('slug')->all())
        ->toBe(['guide', 'guide/intro', 'guide/advanced', 'about']);
});

it('builds the breadcrumb trail to a node', function () {
    expect(collect(Navigation::breadcrumbs(navTree(), 'guide/advanced'))->pluck('slug')->all())
        ->toBe(['guide', 'guide/advanced']);
});

it('returns empty breadcrumbs for an unknown slug', function () {
    expect(Navigation::breadcrumbs(navTree(), 'ghost'))->toBe([]);
});

it('finds previous and next siblings', function () {
    [$prev, $next] = Navigation::siblings(navTree(), 'guide/intro');

    expect(($nullsafeVariable1 = $prev) ? $nullsafeVariable1->slug : null)->toBe('guide')
        ->and(($nullsafeVariable2 = $next) ? $nullsafeVariable2->slug : null)->toBe('guide/advanced');
});

it('returns nulls around an unknown slug', function () {
    expect(Navigation::siblings(navTree(), 'ghost'))->toBe([null, null]);
});

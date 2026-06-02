<?php

declare(strict_types=1);

use Laradocs\Documents\DocumentCollection;
use Laradocs\Documents\DocumentTree;

function tree(DocumentCollection $docs): DocumentTree
{
    return DocumentTree::fromDocuments($docs);
}

it('builds a multi-level tree with sections from index files', function () {
    $docs = new DocumentCollection([
        makeDocument('guide', ['title' => 'Guide'], relativePath: 'guide/_index.md'),
        makeDocument('guide/intro', ['title' => 'Intro', 'order' => 1]),
        makeDocument('guide/deep/nested', ['title' => 'Nested']),
    ]);

    $built = tree($docs);
    $guide = $built->roots[0];

    expect($built->roots)->toHaveCount(1)
        ->and($guide->title)->toBe('Guide')
        ->and($guide->isLink())->toBeTrue()
        ->and($guide->children[0]->slug)->toBe('guide/intro')
        ->and($guide->children[1]->slug)->toBe('guide/deep');
});

it('captures the root index document', function () {
    $docs = new DocumentCollection([
        makeDocument('', ['title' => 'Home'], relativePath: '_index.md'),
        makeDocument('about', ['title' => 'About']),
    ]);

    expect(tree($docs)->rootDocument?->title())->toBe('Home');
});

it('orders siblings by order then title', function () {
    $docs = new DocumentCollection([
        makeDocument('c', ['title' => 'C', 'order' => 1]),
        makeDocument('a', ['title' => 'A', 'order' => 2]),
        makeDocument('b', ['title' => 'B', 'order' => 1]),
    ]);

    expect(collect(tree($docs)->navigation())->pluck('slug')->all())->toBe(['b', 'c', 'a']);
});

it('excludes hidden leaves from navigation', function () {
    $docs = new DocumentCollection([
        makeDocument('visible', ['title' => 'Visible']),
        makeDocument('secret', ['title' => 'Secret', 'hidden' => true]),
    ]);

    expect(collect(tree($docs)->navigation())->pluck('slug')->all())->toBe(['visible']);
});

it('keeps a section when its index is hidden but children are visible', function () {
    $docs = new DocumentCollection([
        makeDocument('guide', ['title' => 'Guide', 'hidden' => true], relativePath: 'guide/_index.md'),
        makeDocument('guide/intro', ['title' => 'Intro']),
    ]);

    $nav = tree($docs)->navigation();

    expect($nav)->toHaveCount(1)
        ->and($nav[0]->slug)->toBe('guide')
        ->and($nav[0]->children)->toHaveCount(1);
});

it('groups top-level navigation nodes by metadata group', function () {
    $docs = new DocumentCollection([
        makeDocument('a', ['title' => 'A', 'group' => 'Start']),
        makeDocument('b', ['title' => 'B']),
    ]);

    $grouped = tree($docs)->grouped();

    expect($grouped->keys()->all())->toContain('Start', '');
});

it('promotes an existing section node to a link when a matching doc loads later', function () {
    $docs = new DocumentCollection([
        makeDocument('api/users', ['title' => 'Users']),
        makeDocument('api', ['title' => 'API Overview']),
    ]);

    $root = tree($docs)->roots[0];

    expect($root->slug)->toBe('api')
        ->and($root->isLink())->toBeTrue()
        ->and($root->title)->toBe('API Overview')
        ->and($root->children)->toHaveCount(1);
});

<?php

declare(strict_types=1);

use Laradocs\Contracts\DocumentLoader;

it('returns an empty collection when the docs directory is missing', function () {
    config()->set('laradocs.docs.path', '/no/such/place');

    expect(app(DocumentLoader::class)->all())->toHaveCount(0);
});

it('loads flat and nested markdown files', function () {
    $this->makeDocs([
        'intro.md' => '# Intro',
        'guide/getting-started.md' => '# Start',
        'guide/deep/nested.md' => '# Deep',
    ]);

    $slugs = app(DocumentLoader::class)->all()->pluck('slug')->sort()->values()->all();

    expect($slugs)->toBe(['guide/deep/nested', 'guide/getting-started', 'intro']);
});

it('honours ignored patterns and extensions', function () {
    $this->makeDocs([
        'keep.md' => '# Keep',
        'README.md' => '# Ignored by pattern',
        '_drafts/wip.md' => '# Draft',
        'notes.txt' => 'not markdown',
    ]);

    $slugs = app(DocumentLoader::class)->all()->pluck('slug')->all();

    expect($slugs)->toContain('keep')
        ->and($slugs)->not->toContain('readme')
        ->and(collect($slugs)->filter(function ($s) {
            return strpos($s, 'wip') !== false;
        }))->toBeEmpty();
});

it('finds a document by slug and records its mtime', function () {
    $this->makeDocs(['guide/intro.md' => "---\ntitle: Intro\n---\nBody"]);

    $doc = app(DocumentLoader::class)->find('guide/intro');

    expect(($nullsafeVariable1 = $doc) ? $nullsafeVariable1->title() : null)->toBe('Intro')
        ->and(($nullsafeVariable2 = $doc) ? $nullsafeVariable2->modifiedAt : null)->toBeGreaterThan(0)
        ->and(app(DocumentLoader::class)->find('missing'))->toBeNull();
});

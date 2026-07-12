<?php

declare(strict_types=1);

use Laradocs\Contracts\DocumentParser;
use Laradocs\Laradocs;

it('boots the provider and merges config', function () {
    expect(config('laradocs.route.prefix'))->toBe('docs')
        ->and(config('laradocs.docs.index'))->toBe('_index');
});

it('resolves the core service from the container', function () {
    expect(app(Laradocs::class))->toBeInstanceOf(Laradocs::class);
});

it('renders markdown to html through the parser', function () {
    $html = app(DocumentParser::class)->parse("# Hello\n\nA **bold** word.");

    expect($html)->toContain('Hello')
        ->and($html)->toContain('<strong>bold</strong>');
});

it('loads and renders a document end to end', function () {
    $this->makeDocs([
        'index.md' => "---\ntitle: Home\norder: 1\n---\n# Welcome\n",
        'guide/intro.md' => "---\ntitle: Intro\n---\n## Getting started\n",
    ]);

    $laradocs = app(Laradocs::class);

    expect($laradocs->all())->toHaveCount(2)
        ->and(($nullsafeVariable1 = $laradocs->find('guide/intro')) ? $nullsafeVariable1->html : null)->toContain('Getting started');
});

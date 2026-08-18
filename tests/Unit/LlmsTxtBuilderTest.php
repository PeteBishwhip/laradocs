<?php

declare(strict_types=1);

use Laradocs\Documents\DocumentCollection;
use Laradocs\Documents\DocumentTree;
use Laradocs\Routing\LlmsTxtBuilder;

function llmsTree(): DocumentTree
{
    return DocumentTree::fromDocuments(new DocumentCollection([
        makeDocument('intro', ['title' => 'Intro']),
    ]));
}

beforeEach(function () {
    config()->set('laradocs.versions.enabled', true);
    config()->set('laradocs.versions.strategy', 'config');
    config()->set('laradocs.versions.default', 'v2');
    config()->set('laradocs.versions.available', [
        'v2' => ['label' => 'v2.0'],
        'v1' => ['label' => 'v1.0'],
    ]);
});

it('excludes non-default version pages from llms.txt by default', function () {
    config()->set('laradocs._current_version', 'v1');

    $body = (new LlmsTxtBuilder)->build(llmsTree());

    expect($body)->not->toContain('- [');
});

it('includes the default version pages', function () {
    config()->set('laradocs._current_version', 'v2');

    expect((new LlmsTxtBuilder)->build(llmsTree()))->toContain('intro');
});

it('includes non-default versions when sitemap_all_versions is true', function () {
    config()->set('laradocs._current_version', 'v1');
    config()->set('laradocs.seo.sitemap_all_versions', true);

    expect((new LlmsTxtBuilder)->build(llmsTree()))->toContain('intro');
});

it('includes pages when versioning is disabled', function () {
    config()->set('laradocs.versions.enabled', false);
    config()->set('laradocs._current_version', 'v1');

    expect((new LlmsTxtBuilder)->build(llmsTree()))->toContain('intro');
});

it('still emits the header when every page is excluded', function () {
    config()->set('laradocs._current_version', 'v1');
    config()->set('laradocs.seo.site_name', 'Acme Docs');

    $body = (new LlmsTxtBuilder)->build(llmsTree());

    // A valid llms.txt needs its H1 even when the index below it is empty, so
    // an excluded version returns a well-formed file rather than a blank one.
    expect($body)->toBe("# Acme Docs\n");
});

it('omits an empty root list rather than emitting a bare Docs heading', function () {
    config()->set('laradocs.versions.enabled', false);

    $tree = DocumentTree::fromDocuments(new DocumentCollection([
        makeDocument('guide', ['title' => 'Guide'], relativePath: 'guide/_index.md'),
        makeDocument('guide/intro', ['title' => 'Intro']),
    ]));

    $body = (new LlmsTxtBuilder)->build($tree);

    expect($body)->toContain('## Guide')
        ->and($body)->not->toContain('## Docs');
});

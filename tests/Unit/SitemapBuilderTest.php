<?php

declare(strict_types=1);

use Laradocs\Documents\DocumentCollection;
use Laradocs\Documents\DocumentTree;
use Laradocs\Routing\SitemapBuilder;

function sitemapTree(): DocumentTree
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

it('excludes non-default version pages from the sitemap by default', function () {
    config()->set('laradocs._current_version', 'v1');

    $body = (new SitemapBuilder)->build(sitemapTree());

    expect($body)->not->toContain('<loc>');
});

it('includes the default version pages', function () {
    config()->set('laradocs._current_version', 'v2');

    $body = (new SitemapBuilder)->build(sitemapTree());

    expect($body)->toContain('intro');
});

it('includes non-default versions when sitemap_all_versions is true', function () {
    config()->set('laradocs._current_version', 'v1');
    config()->set('laradocs.seo.sitemap_all_versions', true);

    $body = (new SitemapBuilder)->build(sitemapTree());

    expect($body)->toContain('intro');
});

it('includes pages when versioning is disabled', function () {
    config()->set('laradocs.versions.enabled', false);
    config()->set('laradocs._current_version', 'v1');

    $body = (new SitemapBuilder)->build(sitemapTree());

    expect($body)->toContain('intro');
});

it('excludes slugs matching seo.sitemap_exclude', function () {
    config()->set('laradocs.versions.enabled', false);
    config()->set('laradocs.seo.sitemap_exclude', ['api/broadcasting/*']);

    $tree = DocumentTree::fromDocuments(new DocumentCollection([
        makeDocument('intro', ['title' => 'Intro']),
        makeDocument('api/broadcasting/auth', ['title' => 'Broadcasting Auth']),
    ]));

    $body = (new SitemapBuilder)->build($tree);

    expect($body)->toContain('intro')
        ->and($body)->not->toContain('broadcasting');
});

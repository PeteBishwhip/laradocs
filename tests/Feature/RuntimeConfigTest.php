<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Laradocs\Cache\DocumentCache;
use Laradocs\Contracts\DocumentLoader;
use Laradocs\Facades\Laradocs;
use Laradocs\Support\CacheKey;

it('reads laradocs.docs.path on every loader call so per-request retargeting works', function () {
    $first = $this->makeDocs(['intro.md' => '# First']);
    $loader = app(DocumentLoader::class);

    expect($loader->all()->pluck('slug')->all())->toBe(['intro']);

    // Point docs.path at a different directory between calls; the same loader
    // instance must pick up the new value, no container rebuild required.
    $this->makeDocs(['guide.md' => '# Second']);

    expect($loader->all()->pluck('slug')->all())->toBe(['guide']);
});

it('returns documents from the new docs.path via the Laradocs facade', function () {
    $this->makeDocs(['alpha.md' => '# Alpha']);
    $alpha = Laradocs::all()->pluck('slug')->all();

    $this->makeDocs(['beta.md' => '# Beta']);
    $beta = Laradocs::all()->pluck('slug')->all();

    expect($alpha)->toBe(['alpha'])
        ->and($beta)->toBe(['beta']);
});

it('writes cache entries under the current laradocs.cache.key_prefix', function () {
    $store = new ArrayStore;
    $cache = new DocumentCache(new Repository($store), enabled: true);
    $doc = makeDocument('a');

    config()->set('laradocs.cache.key_prefix', 'tenant_a');
    $cache->rememberHtml($doc, fn (): string => '<p>A</p>');

    expect($store->get('tenant_a:doc:' . hash('sha256', $doc->path) . ':' . $doc->modifiedAt))
        ->toBe('<p>A</p>');

    // Changing the prefix produces a fresh key (no hit on the old prefix), so
    // the new render is written instead of a cached hit being returned.
    config()->set('laradocs.cache.key_prefix', 'tenant_b');
    $cache->rememberHtml($doc, fn (): string => '<p>B</p>');

    expect($store->get('tenant_b:doc:' . hash('sha256', $doc->path) . ':' . $doc->modifiedAt))
        ->toBe('<p>B</p>')
        ->and($store->get('tenant_a:doc:' . hash('sha256', $doc->path) . ':' . $doc->modifiedAt))
        ->toBe('<p>A</p>');
});

it('routes every cache key through CacheKey so the prefix updates uniformly', function () {
    $doc = makeDocument('a');

    config()->set('laradocs.cache.key_prefix', 'first');

    expect(CacheKey::prefix())->toBe('first')
        ->and(CacheKey::document($doc))->toStartWith('first:doc:')
        ->and(CacheKey::tree('abc'))->toBe('first:tree:abc')
        ->and(CacheKey::search('abc'))->toBe('first:search:abc')
        ->and(CacheKey::sitemap('abc'))->toBe('first:sitemap:abc')
        ->and(CacheKey::index())->toBe('first:index');

    config()->set('laradocs.cache.key_prefix', 'second');

    expect(CacheKey::prefix())->toBe('second')
        ->and(CacheKey::document($doc))->toStartWith('second:doc:')
        ->and(CacheKey::tree('abc'))->toBe('second:tree:abc')
        ->and(CacheKey::search('abc'))->toBe('second:search:abc')
        ->and(CacheKey::sitemap('abc'))->toBe('second:sitemap:abc')
        ->and(CacheKey::index())->toBe('second:index');
});

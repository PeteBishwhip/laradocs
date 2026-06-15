<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Laradocs\Cache\DocumentCache;
use Laradocs\Documents\Document;
use Laradocs\Documents\DocumentCollection;
use Laradocs\Documents\DocumentTree;
use Laradocs\Documents\TreeNode;
use Laradocs\Metadata\Metadata;

function arrayCache(bool $enabled = true): DocumentCache
{
    return new DocumentCache(new Repository(new ArrayStore), $enabled, null);
}

it('caches rendered html on a miss and serves it on a hit', function () {
    $cache = arrayCache();
    $doc = makeDocument('a');
    $calls = 0;

    $render = function () use (&$calls): string {
        $calls++;

        return '<p>rendered</p>';
    };

    expect($cache->rememberHtml($doc, $render))->toBe('<p>rendered</p>');
    expect($cache->rememberHtml($doc, $render))->toBe('<p>rendered</p>');
    expect($calls)->toBe(1);
});

it('bypasses the cache entirely when disabled', function () {
    $cache = arrayCache(enabled: false);
    $doc = makeDocument('a');
    $calls = 0;

    $cache->rememberHtml($doc, function () use (&$calls): string {
        $calls++;

        return 'x';
    });
    $cache->rememberHtml($doc, function () use (&$calls): string {
        $calls++;

        return 'x';
    });

    expect($calls)->toBe(2)
        ->and($cache->isEnabled())->toBeFalse();
});

it('invalidates when a file mtime changes', function () {
    $cache = arrayCache();

    $before = new Document('/x.md', 'x.md', 'x', Metadata::fromArray([]), '', null, 100);
    $after = new Document('/x.md', 'x.md', 'x', Metadata::fromArray([]), '', null, 200);

    expect($cache->documentKey($before))->not->toBe($cache->documentKey($after));

    $cache->rememberHtml($before, fn (): string => 'old');

    expect($cache->rememberHtml($after, fn (): string => 'new'))->toBe('new');
});

it('flushes all tracked entries', function () {
    $cache = arrayCache();
    $doc = makeDocument('a');

    $cache->rememberHtml($doc, fn (): string => 'first');
    $cache->flush();

    expect($cache->rememberHtml($doc, fn (): string => 'second'))->toBe('second');
});

it('caches the tree keyed by combined mtimes', function () {
    $cache = arrayCache();
    $docs = new DocumentCollection([makeDocument('a'), makeDocument('b')]);
    $calls = 0;

    $build = function () use (&$calls): DocumentTree {
        $calls++;

        return new DocumentTree([new TreeNode('A', 'a')]);
    };

    $first = $cache->rememberTree($docs, $build);
    $second = $cache->rememberTree($docs, $build);

    expect($calls)->toBe(1)
        ->and($first)->toBeInstanceOf(DocumentTree::class)
        ->and($second)->toBeInstanceOf(DocumentTree::class);
});

it('survives a cache store that forbids unserializing objects', function () {
    // Mirrors Laravel's `cache.serializable_classes => false` security setting,
    // which makes the default cache return __PHP_Incomplete_Class for any
    // cached object. The package must store the tree as a serialized string
    // and unserialize it itself.
    $store = new class extends ArrayStore
    {
        public function get($key): mixed
        {
            $value = parent::get($key);

            return is_string($value)
                ? unserialize($value, ['allowed_classes' => false])
                : $value;
        }
    };

    $cache = new DocumentCache(new Repository($store), true, null);
    $docs = new DocumentCollection([makeDocument('a')]);
    $tree = new DocumentTree([new TreeNode('A', 'a')]);

    $cache->rememberTree($docs, fn (): DocumentTree => $tree);
    $hit = $cache->rememberTree($docs, fn (): DocumentTree => $tree);

    expect($hit)->toBeInstanceOf(DocumentTree::class)
        ->and($hit)->not->toBeInstanceOf(__PHP_Incomplete_Class::class);
});

it('laradocs:cache warms the cache and laradocs:clear empties it', function () {
    config()->set('laradocs.cache.enabled', true);
    config()->set('laradocs.cache.store', 'array');
    $this->makeDocs(['a.md' => '# A', 'b.md' => '# B']);

    $this->artisan('laradocs:cache')->expectsOutputToContain('Cached 2')->assertSuccessful();
    $this->artisan('laradocs:clear')->assertSuccessful();
});

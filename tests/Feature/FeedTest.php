<?php

declare(strict_types=1);

use Illuminate\Contracts\Cache\Repository;
use Laradocs\Cache\DocumentCache;

// ── RSS (default) ─────────────────────────────────────────────────────────────

it('serves feed.xml with an rss content type by default', function () {
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $response = $this->get('/docs/feed.xml');

    $response->assertOk();
    expect((string) $response->headers->get('Content-Type'))->toStartWith('application/rss+xml');
});

it('emits a valid rss 2.0 document', function () {
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $body = $this->get('/docs/feed.xml')->assertOk()->getContent();

    expect($body)
        ->toStartWith('<?xml version="1.0" encoding="UTF-8"?>')
        ->toContain('<rss version="2.0"')
        ->toContain('<channel>')
        ->toContain('</channel>')
        ->toContain('</rss>');
});

it('lists visible documents as rss items', function () {
    $this->makeDocs([
        'a.md' => "---\ntitle: Alpha\n---\nbody\n",
        'b.md' => "---\ntitle: Beta\n---\nbody\n",
    ]);

    $body = $this->get('/docs/feed.xml')->getContent();

    expect($body)
        ->toContain('<title>Alpha</title>')
        ->toContain('<title>Beta</title>')
        ->toContain('<link>' . url('/docs/a') . '</link>')
        ->toContain('<link>' . url('/docs/b') . '</link>');
});

it('includes guid and pubDate in rss items', function () {
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $body = $this->get('/docs/feed.xml')->getContent();

    expect($body)
        ->toContain('<guid isPermaLink="true">' . url('/docs/a') . '</guid>')
        ->toContain('<pubDate>');
});

it('includes rss description when front-matter description is set', function () {
    $this->makeDocs(['a.md' => "---\ntitle: A\ndescription: Short summary.\n---\nbody\n"]);

    $body = $this->get('/docs/feed.xml')->getContent();

    expect($body)->toContain('<description>Short summary.</description>');
});

it('includes rss author when front-matter author is set', function () {
    $this->makeDocs(['a.md' => "---\ntitle: A\nauthor: Jane\n---\nbody\n"]);

    $body = $this->get('/docs/feed.xml')->getContent();

    expect($body)->toContain('<author>Jane</author>');
});

it('includes a self-referencing atom:link in rss', function () {
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $body = $this->get('/docs/feed.xml')->getContent();

    expect($body)->toContain('rel="self"')
        ->toContain('type="application/rss+xml"');
});

// ── Atom ──────────────────────────────────────────────────────────────────────

it('serves feed.xml with an atom content type when format is atom', function () {
    config()->set('laradocs.feed.format', 'atom');
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $response = $this->get('/docs/feed.xml');

    $response->assertOk();
    expect((string) $response->headers->get('Content-Type'))->toStartWith('application/atom+xml');
});

it('emits a valid atom 1.0 document', function () {
    config()->set('laradocs.feed.format', 'atom');
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $body = $this->get('/docs/feed.xml')->assertOk()->getContent();

    expect($body)
        ->toStartWith('<?xml version="1.0" encoding="UTF-8"?>')
        ->toContain('<feed xmlns="http://www.w3.org/2005/Atom">')
        ->toContain('</feed>');
});

it('lists visible documents as atom entries', function () {
    config()->set('laradocs.feed.format', 'atom');
    $this->makeDocs([
        'a.md' => "---\ntitle: Alpha\n---\nbody\n",
        'b.md' => "---\ntitle: Beta\n---\nbody\n",
    ]);

    $body = $this->get('/docs/feed.xml')->getContent();

    expect($body)
        ->toContain('<title>Alpha</title>')
        ->toContain('<entry>')
        ->toContain('<updated>');
});

it('includes atom summary when front-matter description is set', function () {
    config()->set('laradocs.feed.format', 'atom');
    $this->makeDocs(['a.md' => "---\ntitle: A\ndescription: Short summary.\n---\nbody\n"]);

    $body = $this->get('/docs/feed.xml')->getContent();

    expect($body)->toContain('<summary>Short summary.</summary>');
});

it('includes atom author when front-matter author is set', function () {
    config()->set('laradocs.feed.format', 'atom');
    $this->makeDocs(['a.md' => "---\ntitle: A\nauthor: Jane\n---\nbody\n"]);

    $body = $this->get('/docs/feed.xml')->getContent();

    expect($body)->toContain('<author><name>Jane</name></author>');
});

// ── Filtering ─────────────────────────────────────────────────────────────────

it('excludes hidden documents from the feed', function () {
    $this->makeDocs([
        'visible.md' => "---\ntitle: Visible\n---\nbody\n",
        'secret.md' => "---\ntitle: Secret\nhidden: true\n---\nShh.\n",
    ]);

    $body = $this->get('/docs/feed.xml')->getContent();

    expect($body)->toContain('Visible')
        ->not->toContain('Secret');
});

it('excludes redirected documents from the feed', function () {
    $this->makeDocs([
        'keep.md' => "---\ntitle: Keep\n---\nbody\n",
        'old.md' => "---\ntitle: Old\nredirect: keep\n---\n",
    ]);

    $body = $this->get('/docs/feed.xml')->getContent();

    expect($body)->toContain('Keep')
        ->not->toContain('Old');
});

// ── Ordering ──────────────────────────────────────────────────────────────────

it('sorts items by updated_at front-matter descending', function () {
    $this->makeDocs([
        'old.md' => "---\ntitle: Old\nupdated_at: \"2023-01-01\"\n---\nbody\n",
        'new.md' => "---\ntitle: New\nupdated_at: \"2024-06-01\"\n---\nbody\n",
        'mid.md' => "---\ntitle: Mid\nupdated_at: \"2023-12-01\"\n---\nbody\n",
    ]);

    $body = $this->get('/docs/feed.xml')->getContent();

    $newPos = strpos($body, 'New');
    $midPos = strpos($body, 'Mid');
    $oldPos = strpos($body, 'Old');

    expect($newPos)->toBeLessThan($midPos)
        ->and($midPos)->toBeLessThan($oldPos);
});

it('honours front-matter updated_at in rss pubDate', function () {
    $this->makeDocs([
        'a.md' => "---\ntitle: A\nupdated_at: \"2024-05-15\"\n---\nbody\n",
    ]);

    $body = $this->get('/docs/feed.xml')->getContent();

    // RFC 2822 date for 2024-05-15 starts with the day-of-week
    expect($body)->toContain('<pubDate>')
        ->toContain('2024');
});

it('honours front-matter updated_at in atom updated', function () {
    config()->set('laradocs.feed.format', 'atom');
    $this->makeDocs([
        'a.md' => "---\ntitle: A\nupdated_at: \"2024-05-15\"\n---\nbody\n",
    ]);

    $body = $this->get('/docs/feed.xml')->getContent();

    expect($body)->toContain('2024-05-15');
});

// ── Limit ─────────────────────────────────────────────────────────────────────

it('respects the configured feed limit', function () {
    config()->set('laradocs.feed.limit', 2);

    $this->makeDocs([
        'a.md' => "---\ntitle: A\nupdated_at: \"2024-01-01\"\n---\nbody\n",
        'b.md' => "---\ntitle: B\nupdated_at: \"2024-02-01\"\n---\nbody\n",
        'c.md' => "---\ntitle: C\nupdated_at: \"2024-03-01\"\n---\nbody\n",
    ]);

    $body = $this->get('/docs/feed.xml')->getContent();

    // C and B are the two most recent; A should be excluded
    expect($body)->toContain('<title>C</title>')
        ->toContain('<title>B</title>')
        ->not->toContain('<title>A</title>');
    expect(substr_count($body, '<item>'))->toBe(2);
});

// ── Caching ───────────────────────────────────────────────────────────────────

it('caches the feed and busts it when documents change', function () {
    config()->set('laradocs.cache.enabled', true);

    $root = $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $first = $this->get('/docs/feed.xml')->getContent();
    expect($first)->toContain('/docs/a');

    file_put_contents($root . '/b.md', "---\ntitle: B\n---\nbody\n");
    touch($root . '/b.md', time() + 60);

    $second = $this->get('/docs/feed.xml')->getContent();
    expect($second)->toContain('/docs/b');
});

it('clears the cached feed when laradocs:clear runs', function () {
    config()->set('laradocs.cache.enabled', true);

    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $this->get('/docs/feed.xml')->assertOk();

    $this->artisan('laradocs:clear')->assertSuccessful();

    $cache = app(DocumentCache::class);
    $repo = (new ReflectionClass($cache))->getProperty('store');
    $repo->setAccessible(true);
    /** @var Repository $store */
    $store = $repo->getValue($cache);

    $found = false;
    foreach ((array) $store->get('laradocs:index', []) as $key) {
        if (str_starts_with((string) $key, 'laradocs:feed:')) {
            $found = true;
            break;
        }
    }

    expect($found)->toBeFalse();
});

it('rss and atom feeds use separate cache entries', function () {
    config()->set('laradocs.cache.enabled', true);

    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    config()->set('laradocs.feed.format', 'rss');
    $rss = $this->get('/docs/feed.xml')->getContent();

    config()->set('laradocs.feed.format', 'atom');
    $atom = $this->get('/docs/feed.xml')->getContent();

    expect($rss)->toContain('<rss')
        ->and($atom)->toContain('<feed');
});

// ── Access control ────────────────────────────────────────────────────────────

it('feed 404s when docs are disabled', function () {
    config()->set('laradocs.enabled', false);
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $this->get('/docs/feed.xml')->assertNotFound();
});

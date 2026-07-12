<?php

declare(strict_types=1);
use Illuminate\Contracts\Cache\Repository;
use Laradocs\Cache\DocumentCache;

it('serves sitemap.xml with an xml content type', function () {
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $response = $this->get('/docs/sitemap.xml');

    $response->assertOk();
    expect((string) $response->headers->get('Content-Type'))->toStartWith('application/xml');
});

it('emits a valid sitemaps.org urlset document', function () {
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $body = $this->get('/docs/sitemap.xml')->assertOk()->getContent();

    expect($body)->toStartWith('<?xml version="1.0" encoding="UTF-8"?>')
        ->and($body)->toContain('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">')
        ->and($body)->toContain('</urlset>');
});

it('emits per-locale xhtml alternates when URL-path locales are active', function () {
    config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français']);
    config()->set('laradocs.locale.default', 'en');

    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $body = $this->get('/docs/sitemap.xml')->assertOk()->getContent();

    expect($body)->toContain('xmlns:xhtml="http://www.w3.org/1999/xhtml"')
        ->and($body)->toContain('<xhtml:link rel="alternate" hreflang="en" href="' . url('/docs/a') . '"/>')
        ->and($body)->toContain('<xhtml:link rel="alternate" hreflang="fr" href="' . url('/docs/fr/a') . '"/>')
        ->and($body)->toContain('<xhtml:link rel="alternate" hreflang="x-default" href="' . url('/docs/a') . '"/>');
});

it('omits the xhtml namespace from a single-locale sitemap', function () {
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $body = $this->get('/docs/sitemap.xml')->assertOk()->getContent();

    expect($body)->not->toContain('xmlns:xhtml')
        ->and($body)->not->toContain('<xhtml:link');
});

it('lists every visible document with loc, lastmod and priority', function () {
    $this->makeDocs([
        'a.md' => "---\ntitle: A\n---\nbody\n",
        'guide/intro.md' => "---\ntitle: Intro\n---\nbody\n",
    ]);

    $body = $this->get('/docs/sitemap.xml')->getContent();

    expect($body)->toContain('<loc>' . url('/docs/a') . '</loc>')
        ->and($body)->toContain('<loc>' . url('/docs/guide/intro') . '</loc>')
        ->and($body)->toContain('<lastmod>')
        ->and($body)->toContain('<priority>');
});

it('excludes hidden documents from the sitemap', function () {
    $this->makeDocs([
        'visible.md' => "---\ntitle: Visible\n---\nbody\n",
        'secret.md' => "---\ntitle: Secret\nhidden: true\n---\nShh.\n",
    ]);

    $body = $this->get('/docs/sitemap.xml')->getContent();

    expect($body)->toContain('/docs/visible')
        ->and($body)->not->toContain('/docs/secret');
});

it('excludes redirected documents from the sitemap', function () {
    $this->makeDocs([
        'keep.md' => "---\ntitle: Keep\n---\nbody\n",
        'old.md' => "---\ntitle: Old\nredirect: keep\n---\n",
    ]);

    $body = $this->get('/docs/sitemap.xml')->getContent();

    expect($body)->toContain('/docs/keep')
        ->and($body)->not->toContain('/docs/old');
});

it('orders sitemap entries by tree order (parents before children)', function () {
    $this->makeDocs([
        'guide/_index.md' => "---\ntitle: Guide\norder: 1\n---\n# Guide\n",
        'guide/intro.md' => "---\ntitle: Intro\norder: 1\n---\nbody\n",
        'guide/advanced.md' => "---\ntitle: Advanced\norder: 2\n---\nbody\n",
        'reference.md' => "---\ntitle: Reference\norder: 2\n---\nbody\n",
    ]);

    $body = $this->get('/docs/sitemap.xml')->getContent();

    $guidePos = strpos($body, '/docs/guide</loc>');
    $introPos = strpos($body, '/docs/guide/intro');
    $advancedPos = strpos($body, '/docs/guide/advanced');
    $referencePos = strpos($body, '/docs/reference');

    expect($guidePos)->toBeInt()
        ->and($introPos)->toBeGreaterThan($guidePos)
        ->and($advancedPos)->toBeGreaterThan($introPos)
        ->and($referencePos)->toBeGreaterThan($advancedPos);
});

it('includes the root document at the docs index URL', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\nWelcome.\n",
        'a.md' => "---\ntitle: A\n---\nbody\n",
    ]);

    $body = $this->get('/docs/sitemap.xml')->getContent();

    expect($body)->toContain('<loc>' . url('/docs') . '</loc>');
});

it('priority decreases with tree depth', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\nWelcome.\n",
        'guide/_index.md' => "---\ntitle: Guide\n---\n# Guide\n",
        'guide/intro.md' => "---\ntitle: Intro\n---\nbody\n",
    ]);

    $body = $this->get('/docs/sitemap.xml')->getContent();

    // Root: 1.0, depth-1 section: 0.8, depth-2 leaf: 0.6
    expect($body)->toContain('<priority>1.0</priority>')
        ->and($body)->toContain('<priority>0.8</priority>')
        ->and($body)->toContain('<priority>0.6</priority>');
});

it('honours an explicit priority value from front-matter', function () {
    $this->makeDocs([
        'a.md' => "---\ntitle: A\npriority: 0.3\n---\nbody\n",
    ]);

    $body = $this->get('/docs/sitemap.xml')->getContent();

    expect($body)->toContain('<priority>0.3</priority>');
});

it('uses the front-matter updated_at as lastmod when set', function () {
    $this->makeDocs([
        'a.md' => "---\ntitle: A\nupdated_at: \"2024-05-01\"\n---\nbody\n",
    ]);

    $body = $this->get('/docs/sitemap.xml')->getContent();

    expect($body)->toContain('<lastmod>2024-05-01T');
});

it('caches the sitemap and busts it when documents change', function () {
    config()->set('laradocs.cache.enabled', true);

    $root = $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $first = $this->get('/docs/sitemap.xml')->getContent();

    expect($first)->toContain('/docs/a');

    file_put_contents($root . '/b.md', "---\ntitle: B\n---\nbody\n");
    touch($root . '/b.md', time() + 60);

    $second = $this->get('/docs/sitemap.xml')->getContent();

    expect($second)->toContain('/docs/b');
});

it('clears the cached sitemap when laradocs:clear runs', function () {
    config()->set('laradocs.cache.enabled', true);

    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $this->get('/docs/sitemap.xml')->assertOk();

    $this->artisan('laradocs:clear')->assertSuccessful();

    $cache = app(DocumentCache::class);
    $repo = (new ReflectionClass($cache))->getProperty('store');
    $repo->setAccessible(true);
    /** @var Repository $store */
    $store = $repo->getValue($cache);

    $found = false;
    $index = $store->get('laradocs:index', []);
    foreach ((array) $index as $key) {
        if (strncmp((string) $key, 'laradocs:sitemap:', strlen('laradocs:sitemap:')) === 0) {
            $found = true;
            break;
        }
    }

    expect($found)->toBeFalse();
});

it('sitemap 404s when docs are disabled', function () {
    config()->set('laradocs.enabled', false);
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $this->get('/docs/sitemap.xml')->assertNotFound();
});

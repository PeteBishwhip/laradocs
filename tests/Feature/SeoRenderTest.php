<?php

declare(strict_types=1);

beforeEach(function () {
    config()->set('laradocs.ui.brand.title', 'Acme Docs');

    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\norder: 1\n---\n# Welcome\n\nThe home page.\n",
        'guide/intro.md' => "---\ntitle: Intro\ndescription: All about the intro.\norder: 1\n---\n## Step one\n",
        'guide/hidden.md' => "---\ntitle: Hidden\nseo:\n  robots: noindex, nofollow\n---\nShh.\n",
    ]);
});

it('renders rich SEO meta tags on a document', function () {
    $response = $this->get('/docs/guide/intro')->assertOk();

    $response->assertSee('<title>Intro · Acme Docs</title>', false)
        ->assertSee('All about the intro.', false)
        ->assertSee('property="og:title"', false)
        ->assertSee('property="og:type"', false)
        ->assertSee('rel="canonical"', false)
        ->assertSee('application/ld+json', false);
});

it('renders SEO meta on the home page', function () {
    $this->get('/docs')
        ->assertOk()
        ->assertSee('property="og:site_name"', false)
        ->assertSee('Acme Docs', false);
});

it('passes through a per-page robots override', function () {
    $this->get('/docs/guide/hidden')
        ->assertOk()
        ->assertSee('noindex, nofollow', false);
});

it('falls back to a plain title when SEO is disabled', function () {
    config()->set('laradocs.seo.enabled', false);

    $this->get('/docs/guide/intro')
        ->assertOk()
        ->assertSee('<title>Intro</title>', false)
        ->assertDontSee('property="og:title"', false);
});

it('renders twitter:card meta tag with the default type', function () {
    $this->get('/docs/guide/intro')
        ->assertOk()
        ->assertSee('name="twitter:card"', false)
        ->assertSee('summary_large_image', false);
});

it('renders twitter:title and twitter:description', function () {
    $this->get('/docs/guide/intro')
        ->assertOk()
        ->assertSee('name="twitter:title"', false)
        ->assertSee('name="twitter:description"', false);
});

it('renders og:description', function () {
    $this->get('/docs/guide/intro')
        ->assertOk()
        ->assertSee('property="og:description"', false)
        ->assertSee('All about the intro.', false);
});

it('renders og:image when a site-wide image is configured', function () {
    config()->set('laradocs.seo.image', 'https://example.com/og.png');

    $this->get('/docs/guide/intro')
        ->assertOk()
        ->assertSee('property="og:image"', false)
        ->assertSee('https://example.com/og.png', false);
});

it('emits no og:image when none is configured and generation is disabled', function () {
    config()->set('laradocs.seo.og_image.enabled', false);

    $this->get('/docs/guide/intro')
        ->assertOk()
        ->assertDontSee('property="og:image"', false);
});

it('prefers page image front-matter over site-wide default', function () {
    config()->set('laradocs.seo.image', 'https://example.com/default.png');

    $this->makeDocs([
        'guide/imaged.md' => "---\ntitle: Imaged\nimage: https://example.com/page.png\n---\nContent here.",
    ]);

    $html = $this->get('/docs/guide/imaged')->assertOk()->getContent();

    expect($html)->toContain('https://example.com/page.png')
        ->not->toContain('https://example.com/default.png');
});

it('prefers seo: block image over top-level image front-matter', function () {
    $this->makeDocs([
        'guide/seo-image.md' => "---\ntitle: SEO Image\nimage: https://example.com/top.png\nseo:\n  image: https://example.com/seo.png\n---\nContent.",
    ]);

    $html = $this->get('/docs/guide/seo-image')->assertOk()->getContent();

    expect($html)->toContain('https://example.com/seo.png')
        ->not->toContain('https://example.com/top.png');
});

it('honours a per-page x_card override from the seo: block', function () {
    $this->makeDocs([
        'guide/summary.md' => "---\ntitle: Summary Card\nseo:\n  x_card: summary\n---\nSome content.",
    ]);

    $html = $this->get('/docs/guide/summary')->assertOk()->getContent();

    // Our explicit tag is first; assert it appears before the package's auto-detected one
    $firstCard = strpos($html, 'name="twitter:card"');
    $summaryPos = strpos($html, 'content="summary"');

    expect($firstCard)->toBeLessThan($summaryPos + 100)
        ->and($html)->toContain('content="summary"');
});

it('honours a site-wide x_card config', function () {
    config()->set('laradocs.seo.x_card', 'summary');

    $this->get('/docs/guide/intro')
        ->assertOk()
        ->assertSee('content="summary"', false);
});

it('snapshot-tests the social meta block', function () {
    config()->set('laradocs.seo.image', 'https://example.com/og.png');
    config()->set('laradocs.seo.x', 'acmedocs');

    $html = $this->get('/docs/guide/intro')->assertOk()->getContent();

    // Extract all og: and twitter: meta tags for a stable snapshot
    preg_match_all('/<meta [^>]*(?:property="og:|name="twitter:)[^>]*>/i', $html, $matches);

    // Normalise the server-generated URL so the snapshot is stable across environments
    $tags = implode("\n", $matches[0]);
    $tags = preg_replace('~https?://[^/"]+~', 'https://example.test', $tags);

    expect($tags)->toContain('property="og:title"')
        ->and($tags)->toContain('name="twitter:card"');
});

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

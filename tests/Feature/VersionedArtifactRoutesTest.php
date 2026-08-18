<?php

declare(strict_types=1);

use Laradocs\Contracts\OgImageGenerator;
use Laradocs\Routing\DocumentUrl;
use Laradocs\Seo\OgImageData;

/**
 * With multi-version docs enabled and the default `versions.unversioned =
 * redirect` policy, every fixed-path route in the docs group used to
 * 301-redirect to the default version's HTML docs root instead of doing its
 * job: SetDocsVersion resolved the version from `$request->route('path')`,
 * which these routes never populate. See GitHub issue #177.
 *
 * Each of these routes now forces the default version active via the
 * `SetDocsVersion::class . ':render'` middleware parameter instead of relying
 * on the (redirect-by-default) unversioned policy.
 */
beforeEach(function () {
    config()->set('laradocs.versions.enabled', true);
    config()->set('laradocs.versions.strategy', 'config');
    config()->set('laradocs.versions.default', 'v2');
    config()->set('laradocs.versions.unversioned', 'redirect');
    config()->set('laradocs.versions.available', [
        'v2' => 'v2.0',
        'v1' => 'v1.0',
    ]);

    $this->makeDocs([
        'v1/intro.md' => "---\ntitle: Intro\n---\nOne body.\n",
        'v2/intro.md' => "---\ntitle: Intro\n---\nTwo body.\n",
    ]);
});

it('serves sitemap.xml instead of redirecting, scoped to the default version', function () {
    $this->get('/docs/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('/docs/v2/intro', false)
        ->assertDontSee('/docs/v1/intro', false);
});

it('serves feed.xml instead of redirecting', function () {
    $this->get('/docs/feed.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
});

it('serves the search endpoint instead of redirecting, scoped to the default version', function () {
    $response = $this->getJson('/docs/_laradocs/search?q=intro')->assertOk();

    $urls = array_column($response->json('results'), 'url');

    expect($urls)->toContain(url('/docs/v2/intro'))
        ->not->toContain(url('/docs/v1/intro'));
});

it('serves the api/tree endpoint instead of redirecting, scoped to the default version', function () {
    $response = $this->getJson('/docs/_laradocs/api/tree')->assertOk();

    $ids = array_column($response->json('data'), 'id');

    expect($ids)->toContain('intro');
});

it('serves the api/search endpoint instead of redirecting, scoped to the default version', function () {
    $response = $this->getJson('/docs/_laradocs/api/search?q=intro')->assertOk();

    $urls = array_column($response->json('data.*.attributes.url'), null);

    expect($urls)->toContain(url('/docs/v2/intro'))
        ->not->toContain(url('/docs/v1/intro'));
});

it('serves the og image index instead of redirecting', function () {
    $this->app->instance(OgImageGenerator::class, new class implements OgImageGenerator
    {
        public function generate(OgImageData $data): string
        {
            return 'FAKE-PNG-BYTES';
        }
    });

    $this->get('/docs/_laradocs/og')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('serves the tags index instead of redirecting, scoped to the default version', function () {
    $this->makeDocs([
        'v1/tagged.md' => "---\ntitle: Tagged\ntags: [alpha]\n---\nBody.\n",
        'v2/tagged.md' => "---\ntitle: Tagged\ntags: [alpha]\n---\nBody.\n",
    ]);

    $this->get('/docs/tags')
        ->assertOk()
        ->assertSee('alpha');
});

it('serves a tag page instead of redirecting, scoped to the default version', function () {
    $this->makeDocs([
        'v1/tagged.md' => "---\ntitle: Tagged\ntags: [alpha]\n---\nBody.\n",
        'v2/tagged.md' => "---\ntitle: Tagged\ntags: [alpha]\n---\nBody.\n",
    ]);

    $this->get('/docs/tag/alpha')
        ->assertOk()
        ->assertSee('Tagged');
});

it("resolves robots.txt's advertised sitemap URL to a 200, not a redirect", function () {
    $robots = $this->get('/docs/robots.txt')->assertOk()->getContent();

    expect($robots)->toContain('Sitemap: ' . DocumentUrl::sitemap());

    $this->get(DocumentUrl::sitemap())->assertOk();
});

<?php

declare(strict_types=1);
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Routing\Registrar;
use Laradocs\Cache\DocumentCache;
use Laradocs\Routing\DocumentRouter;

it('serves llms.txt with a plain-text content type', function () {
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $response = $this->get('/docs/llms.txt');

    $response->assertOk();
    expect((string) $response->headers->get('Content-Type'))->toStartWith('text/plain');
});

it('opens with the site name as an H1', function () {
    config()->set('laradocs.seo.site_name', 'Acme Docs');

    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $body = $this->get('/docs/llms.txt')->getContent();

    expect($body)->toStartWith("# Acme Docs\n");
});

it('falls back to the brand title for the H1', function () {
    config()->set('laradocs.seo.site_name', null);
    config()->set('laradocs.ui.brand.title', 'Brand Docs');

    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    expect($this->get('/docs/llms.txt')->getContent())->toStartWith("# Brand Docs\n");
});

it('treats an empty site name as absent so the brand title still wins', function () {
    config()->set('laradocs.seo.site_name', '');
    config()->set('laradocs.ui.brand.title', 'Brand Docs');

    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    expect($this->get('/docs/llms.txt')->getContent())->toStartWith("# Brand Docs\n");
});

it('uses the site description as the blockquote', function () {
    config()->set('laradocs.seo.description', 'Everything about Acme.');

    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    expect($this->get('/docs/llms.txt')->getContent())->toContain("\n> Everything about Acme.\n");
});

it('falls back to the brand tagline for the blockquote', function () {
    config()->set('laradocs.seo.description', null);
    config()->set('laradocs.ui.brand.tagline', 'The Acme tagline.');

    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    expect($this->get('/docs/llms.txt')->getContent())->toContain("\n> The Acme tagline.\n");
});

it('omits the blockquote when no description resolves', function () {
    config()->set('laradocs.seo.description', null);
    config()->set('laradocs.ui.brand.tagline', null);

    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    expect($this->get('/docs/llms.txt')->getContent())->not->toContain('> ');
});

it('gathers root-level pages under a leading Docs heading', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\ndescription: The index.\n---\nWelcome.\n",
        'install.md' => "---\ntitle: Install\ndescription: How to install.\n---\nbody\n",
    ]);

    $body = $this->get('/docs/llms.txt')->getContent();

    expect($body)->toContain("\n## Docs\n\n")
        ->and($body)->toContain('- [Home](' . url('/docs') . '): The index.')
        ->and($body)->toContain('- [Install](' . url('/docs/install') . '): How to install.');
});

it('emits one H2 per top-level section with the section index listed first', function () {
    $this->makeDocs([
        'guide/_index.md' => "---\ntitle: Guide\ndescription: The guide.\n---\n# Guide\n",
        'guide/intro.md' => "---\ntitle: Intro\ndescription: Start here.\norder: 1\n---\nbody\n",
    ]);

    $body = $this->get('/docs/llms.txt')->getContent();

    $heading = strpos($body, "\n## Guide\n");
    $index = strpos($body, '- [Guide](');
    $intro = strpos($body, '- [Intro](');

    expect($heading)->toBeInt()
        ->and($index)->toBeGreaterThan($heading)
        ->and($intro)->toBeGreaterThan($index);
});

it('lists nested pages under their top-level section in tree order', function () {
    $this->makeDocs([
        'guide/_index.md' => "---\ntitle: Guide\norder: 1\n---\n# Guide\n",
        'guide/intro.md' => "---\ntitle: Intro\norder: 1\n---\nbody\n",
        'guide/advanced/deep.md' => "---\ntitle: Deep\norder: 2\n---\nbody\n",
        'reference.md' => "---\ntitle: Reference\norder: 2\n---\nbody\n",
    ]);

    $body = $this->get('/docs/llms.txt')->getContent();

    $intro = strpos($body, '- [Intro](');
    $deep = strpos($body, '- [Deep](');

    expect($intro)->toBeInt()
        ->and($deep)->toBeGreaterThan($intro)
        // A section placeholder directory with no _index.md contributes no
        // bullet of its own, only its children.
        ->and($body)->not->toContain('- [Advanced](');
});

it('orders sections by tree order', function () {
    $this->makeDocs([
        'zebra/_index.md' => "---\ntitle: Zebra\norder: 1\n---\n# Zebra\n",
        'zebra/page.md' => "---\ntitle: Zebra Page\n---\nbody\n",
        'alpha/_index.md' => "---\ntitle: Alpha\norder: 2\n---\n# Alpha\n",
        'alpha/page.md' => "---\ntitle: Alpha Page\n---\nbody\n",
    ]);

    $body = $this->get('/docs/llms.txt')->getContent();

    expect(strpos($body, '## Zebra'))->toBeLessThan(strpos($body, '## Alpha'));
});

it('derives a bullet description from the page body when front-matter has none', function () {
    $this->makeDocs([
        'a.md' => "---\ntitle: A\n---\n# Heading\n\nThe opening paragraph of the page.\n",
    ]);

    expect($this->get('/docs/llms.txt')->getContent())
        ->toContain('- [A](' . url('/docs/a') . '): The opening paragraph of the page.');
});

it('emits a bare link when no description can be derived', function () {
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\n"]);

    $body = $this->get('/docs/llms.txt')->getContent();

    expect($body)->toContain('- [A](' . url('/docs/a') . ")\n")
        ->and($body)->not->toContain('- [A](' . url('/docs/a') . '):');
});

it('prefers the front-matter description over the body excerpt', function () {
    $this->makeDocs([
        'a.md' => "---\ntitle: A\ndescription: Declared.\n---\nDerived from the body.\n",
    ]);

    $body = $this->get('/docs/llms.txt')->getContent();

    expect($body)->toContain(': Declared.')
        ->and($body)->not->toContain('Derived from the body');
});

it('treats a whitespace-only front-matter description as absent', function () {
    $this->makeDocs([
        'a.md' => "---\ntitle: A\ndescription: \"   \"\n---\nDerived from the body.\n",
    ]);

    expect($this->get('/docs/llms.txt')->getContent())->toContain(': Derived from the body.');
});

it('flattens a multi-line description onto a single line', function () {
    $this->makeDocs([
        'a.md' => "---\ntitle: A\ndescription: \"first line\\nsecond line\"\n---\nbody\n",
    ]);

    $body = $this->get('/docs/llms.txt')->getContent();

    expect($body)->toContain(': first line second line')
        ->and(substr_count($body, '- [A]'))->toBe(1);
});

it('escapes square brackets in a title so the link label survives', function () {
    $this->makeDocs([
        'a.md' => "---\ntitle: \"A [Draft] Page\"\n---\nbody\n",
    ]);

    expect($this->get('/docs/llms.txt')->getContent())->toContain('- [A \[Draft\] Page](');
});

it('excludes hidden documents', function () {
    $this->makeDocs([
        'visible.md' => "---\ntitle: Visible\n---\nbody\n",
        'secret.md' => "---\ntitle: Secret\nhidden: true\n---\nShh.\n",
    ]);

    $body = $this->get('/docs/llms.txt')->getContent();

    expect($body)->toContain('/docs/visible')
        ->and($body)->not->toContain('/docs/secret');
});

it('excludes redirected documents', function () {
    $this->makeDocs([
        'keep.md' => "---\ntitle: Keep\n---\nbody\n",
        'old.md' => "---\ntitle: Old\nredirect: keep\n---\n",
    ]);

    $body = $this->get('/docs/llms.txt')->getContent();

    expect($body)->toContain('/docs/keep')
        ->and($body)->not->toContain('/docs/old');
});

it('drops a section heading whose only page is redirected', function () {
    $this->makeDocs([
        'kept/_index.md' => "---\ntitle: Kept\n---\n# Kept\n",
        'kept/page.md' => "---\ntitle: Page\n---\nbody\n",
        'gone/_index.md' => "---\ntitle: Gone\nredirect: kept\n---\n",
        'gone/page.md' => "---\ntitle: Gone Page\nredirect: kept\n---\n",
    ]);

    $body = $this->get('/docs/llms.txt')->getContent();

    expect($body)->toContain('## Kept')
        ->and($body)->not->toContain('## Gone');
});

it('registers the route under the package name prefix', function () {
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    expect(route('laradocs.llms'))->toBe(url('/docs/llms.txt'));
});

it('caches llms.txt and busts it when documents change', function () {
    config()->set('laradocs.cache.enabled', true);

    $root = $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    expect($this->get('/docs/llms.txt')->getContent())->toContain('/docs/a');

    file_put_contents($root . '/b.md', "---\ntitle: B\n---\nbody\n");
    touch($root . '/b.md', time() + 60);

    expect($this->get('/docs/llms.txt')->getContent())->toContain('/docs/b');
});

it('clears the cached llms.txt when laradocs:clear runs', function () {
    config()->set('laradocs.cache.enabled', true);

    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $this->get('/docs/llms.txt')->assertOk();

    $this->artisan('laradocs:clear')->assertSuccessful();

    $cache = app(DocumentCache::class);
    $repo = (new ReflectionClass($cache))->getProperty('store');
    $repo->setAccessible(true);
    /** @var Repository $store */
    $store = $repo->getValue($cache);

    $found = false;
    foreach ((array) $store->get('laradocs:index', []) as $key) {
        if (str_starts_with((string) $key, 'laradocs:llms:')) {
            $found = true;
            break;
        }
    }

    expect($found)->toBeFalse();
});

it('warms the llms.txt cache in laradocs:cache', function () {
    config()->set('laradocs.cache.enabled', true);

    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $this->artisan('laradocs:cache')->assertSuccessful();

    $cache = app(DocumentCache::class);
    $repo = (new ReflectionClass($cache))->getProperty('store');
    $repo->setAccessible(true);
    /** @var Repository $store */
    $store = $repo->getValue($cache);

    $found = false;
    foreach ((array) $store->get('laradocs:index', []) as $key) {
        if (str_starts_with((string) $key, 'laradocs:llms:')) {
            $found = true;
            break;
        }
    }

    expect($found)->toBeTrue();
});

it('llms.txt 404s when docs are disabled', function () {
    config()->set('laradocs.enabled', false);
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $this->get('/docs/llms.txt')->assertNotFound();
});

it('serves llms.txt while versioning is enabled instead of redirecting', function () {
    config()->set('laradocs.versions.enabled', true);
    config()->set('laradocs.versions.strategy', 'config');
    config()->set('laradocs.versions.default', 'v2');
    config()->set('laradocs.versions.available', [
        'v2' => ['label' => 'v2.0'],
        'v1' => ['label' => 'v1.0'],
    ]);

    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    // The route carries no version segment, so SetDocsVersion's unversioned
    // policy would 301 it to the default version's docs root if it applied.
    $this->get('/docs/llms.txt')->assertOk();
});

it('does not register the llms.txt route when disabled', function () {
    config()->set('laradocs.llms.enabled', false);

    expect(registeredRouteNames('llms-off'))->not->toContain('llms-off.llms')
        ->and(registeredRouteNames('llms-off'))->toContain('llms-off.show');
});

it('does not register a root llms.txt route by default', function () {
    expect(registeredRouteNames('llms-default'))->not->toContain('llms-default.llms.root')
        ->and(registeredRouteNames('llms-default'))->toContain('llms-default.llms');
});

it('registers a root llms.txt route when opted in', function () {
    config()->set('laradocs.llms.root', true);

    expect(registeredRouteNames('llms-root'))->toContain('llms-root.llms.root');
});

it('serves the same body from the root route when opted in', function () {
    config()->set('laradocs.llms.root', true);

    // Re-register so the root route exists for this request cycle.
    (new DocumentRouter)->register(app(Registrar::class), [
        'prefix' => 'docs',
        'name' => 'laradocs.',
        'middleware' => ['web'],
    ]);

    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $response = $this->get('/llms.txt');

    $response->assertOk();
    expect($response->getContent())->toContain('- [A](');
});

it('does not register a root llms.txt route when llms is disabled entirely', function () {
    config()->set('laradocs.llms.enabled', false);
    config()->set('laradocs.llms.root', true);

    expect(registeredRouteNames('llms-both-off'))->not->toContain('llms-both-off.llms.root');
});

/**
 * Register the package routes on a throwaway prefix and return every route
 * name, so tests can assert on registration-time config without rebuilding the
 * application.
 *
 * @return array<int, string>
 */
function registeredRouteNames(string $prefix): array
{
    $router = app(Registrar::class);

    (new DocumentRouter)->register($router, [
        'prefix' => $prefix,
        'name' => $prefix . '.',
        'middleware' => ['web'],
    ]);

    $names = [];

    foreach ($router->getRoutes()->getRoutes() as $route) {
        $name = $route->getName();

        if (is_string($name)) {
            $names[] = $name;
        }
    }

    return $names;
}

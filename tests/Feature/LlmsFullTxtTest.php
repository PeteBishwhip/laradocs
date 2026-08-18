<?php

declare(strict_types=1);

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Routing\Registrar;
use Laradocs\Cache\DocumentCache;
use Laradocs\Routing\DocumentRouter;
use Laradocs\Variables\VariableRegistry;

/**
 * llms-full.txt is opt-in (`laradocs.llms.full`) and gated at route
 * registration time, so a test that wants to reach it over HTTP must
 * re-register the package routes after turning the flag on — the same
 * pattern the llms.txt suite uses for its own opt-in root route.
 *
 * Re-registering on the real "docs" prefix would add the new route *after*
 * the catch-all show route already bound there at boot (when `full` was
 * still off), and the catch-all — registered first — would win. A fresh
 * prefix avoids that ordering hazard.
 *
 * Which of the two registrations then owns a shared route *name* such as
 * "laradocs.show" is deliberately not relied upon — it differs across the
 * supported framework range, so the prefix a generated page link resolves to
 * is not stable across the CI matrix. Assertions on page URLs here are
 * therefore structural; the exact URL construction is pinned by
 * LlmsFullTxtBuilderTest, which builds against one unambiguous registration.
 *
 * The llms-full.txt route name itself is unaffected: `full` is off at boot,
 * so only this helper ever registers "laradocs.llms.full".
 *
 * @return string the prefix requests should be made against
 */
function enableLlmsFull(): string
{
    config()->set('laradocs.llms.full', true);

    (new DocumentRouter)->register(app(Registrar::class), [
        'prefix' => 'llms-full-live',
        'name' => 'laradocs.',
        'middleware' => ['web'],
    ]);

    return 'llms-full-live';
}

it('serves llms-full.txt with a plain-text content type', function () {
    $prefix = enableLlmsFull();
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $response = $this->get("/{$prefix}/llms-full.txt");

    $response->assertOk();
    expect((string) $response->headers->get('Content-Type'))->toStartWith('text/plain');
});

it('opens with the same header llms.txt uses', function () {
    $prefix = enableLlmsFull();
    config()->set('laradocs.seo.site_name', 'Acme Docs');
    config()->set('laradocs.seo.description', 'Everything about Acme.');

    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $body = $this->get("/{$prefix}/llms-full.txt")->getContent();

    expect($body)->toStartWith("# Acme Docs\n\n> Everything about Acme.\n");
});

it('carries each page\'s content instead of a link to it', function () {
    $prefix = enableLlmsFull();
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nThe full body of page A.\n"]);

    $body = $this->get("/{$prefix}/llms-full.txt")->getContent();

    // Asserted structurally rather than against a literal URL: this suite
    // registers the package routes a second time (see enableLlmsFull), and
    // which registration then owns the shared "laradocs.show" route name
    // differs by framework version, so the prefix a page link resolves to is
    // not stable across the CI matrix. What matters here is the entry shape —
    // an H2 naming the page and linking its absolute canonical URL, then the
    // page's content. LlmsFullTxtBuilderTest pins the exact URL construction
    // against a single, unambiguous registration.
    expect($body)->toMatch('/^## \[A\]\(https?:\/\/[^\s)]+\/a\)\n\n/m')
        ->and($body)->toContain('The full body of page A.');
});

it('interpolates variables in each page body', function () {
    $prefix = enableLlmsFull();
    $this->app->make(VariableRegistry::class)->set('product', 'Acme');
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nWelcome to {{ product }}.\n"]);

    $body = $this->get("/{$prefix}/llms-full.txt")->getContent();

    expect($body)->toContain('Welcome to Acme.');
});

it('excludes hidden and redirected documents, same as llms.txt', function () {
    $prefix = enableLlmsFull();
    $this->makeDocs([
        'visible.md' => "---\ntitle: Visible\n---\nVisible body.\n",
        'secret.md' => "---\ntitle: Secret\nhidden: true\n---\nShh.\n",
        'old.md' => "---\ntitle: Old\nredirect: visible\n---\n",
    ]);

    $body = $this->get("/{$prefix}/llms-full.txt")->getContent();

    expect($body)->toContain('Visible body.')
        ->and($body)->not->toContain('Shh.')
        ->and($body)->not->toContain('## [Old]');
});

it('registers the route under the package name prefix', function () {
    $prefix = enableLlmsFull();
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    expect(route('laradocs.llms.full'))->toBe(url("/{$prefix}/llms-full.txt"));
});

it('caches llms-full.txt and busts it when documents change', function () {
    $prefix = enableLlmsFull();
    config()->set('laradocs.cache.enabled', true);

    $root = $this->makeDocs(['a.md' => "---\ntitle: A\n---\nFirst body.\n"]);

    expect($this->get("/{$prefix}/llms-full.txt")->getContent())->toContain('First body.');

    file_put_contents($root . '/a.md', "---\ntitle: A\n---\nSecond body.\n");
    touch($root . '/a.md', time() + 60);

    expect($this->get("/{$prefix}/llms-full.txt")->getContent())->toContain('Second body.');
});

it('clears the cached llms-full.txt when laradocs:clear runs', function () {
    $prefix = enableLlmsFull();
    config()->set('laradocs.cache.enabled', true);

    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $this->get("/{$prefix}/llms-full.txt")->assertOk();

    $this->artisan('laradocs:clear')->assertSuccessful();

    expect(trackedCacheKeyExists('laradocs:llms-full:'))->toBeFalse();
});

it('warms the llms-full.txt cache in laradocs:cache', function () {
    enableLlmsFull();
    config()->set('laradocs.cache.enabled', true);

    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $this->artisan('laradocs:cache')->assertSuccessful();

    expect(trackedCacheKeyExists('laradocs:llms-full:'))->toBeTrue();
});

it('truncates the corpus once full_max_bytes is exceeded', function () {
    $prefix = enableLlmsFull();
    // Generous enough to comfortably fit one entry but not two, so the cut
    // falls cleanly between them rather than mid-page.
    config()->set('laradocs.llms.full_max_bytes', 1200);

    $this->makeDocs([
        'a.md' => "---\ntitle: A\n---\n" . str_repeat('x', 1000) . "\n",
        'b.md' => "---\ntitle: B\n---\n" . str_repeat('y', 1000) . "\n",
    ]);

    $body = $this->get("/{$prefix}/llms-full.txt")->getContent();

    expect($body)->toContain(str_repeat('x', 1000))
        ->and($body)->toContain('Truncated')
        ->and($body)->not->toContain(str_repeat('y', 1000));
});

it('does not truncate when full_max_bytes is 0', function () {
    $prefix = enableLlmsFull();
    config()->set('laradocs.llms.full_max_bytes', 0);

    $this->makeDocs(['a.md' => "---\ntitle: A\n---\n" . str_repeat('x', 500) . "\n"]);

    $body = $this->get("/{$prefix}/llms-full.txt")->getContent();

    expect($body)->not->toContain('Truncated');
});

it('llms-full.txt 404s when docs are disabled', function () {
    $prefix = enableLlmsFull();
    config()->set('laradocs.enabled', false);
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $this->get("/{$prefix}/llms-full.txt")->assertNotFound();
});

it('does not register the llms-full.txt route by default', function () {
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $this->get('/docs/llms-full.txt')->assertNotFound();
});

it('does not register llms-full.txt when llms is disabled entirely, even if full is on', function () {
    config()->set('laradocs.llms.enabled', false);
    config()->set('laradocs.llms.full', true);

    $router = app(Registrar::class);

    (new DocumentRouter)->register($router, [
        'prefix' => 'llms-full-off',
        'name' => 'llms-full-off.',
        'middleware' => ['web'],
    ]);

    $names = [];

    foreach ($router->getRoutes()->getRoutes() as $route) {
        $name = $route->getName();

        if (is_string($name)) {
            $names[] = $name;
        }
    }

    expect($names)->not->toContain('llms-full-off.llms.full');
});

/**
 * Whether a cache key starting with the given prefix exists in the tracked
 * index, used to assert a cache entry was warmed or cleared without depending
 * on cache-store internals beyond the tracked-keys index the package itself
 * maintains.
 */
function trackedCacheKeyExists(string $prefix): bool
{
    $cache = app(DocumentCache::class);
    $property = (new ReflectionClass($cache))->getProperty('store');
    $property->setAccessible(true);
    /** @var Repository $store */
    $store = $property->getValue($cache);

    foreach ((array) $store->get('laradocs:index', []) as $key) {
        if (str_starts_with((string) $key, $prefix)) {
            return true;
        }
    }

    return false;
}

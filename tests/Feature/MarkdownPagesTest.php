<?php

declare(strict_types=1);

use Illuminate\Contracts\Routing\Registrar;
use Laradocs\Laradocs;
use Laradocs\Routing\DocumentRouter;
use Laradocs\Support\CacheKey;
use Laradocs\Variables\VariableRegistry;

/**
 * The `.md` route is opt-in (`laradocs.markdown.enabled`) and gated at route
 * registration time, so a test that wants it over HTTP re-registers the
 * package routes on a fresh prefix after turning the flag on. See
 * LlmsFullTxtTest for why a fresh prefix avoids the catch-all ordering hazard.
 *
 * @return string the prefix requests should be made against
 */
function enableMarkdown(): string
{
    config()->set('laradocs.markdown.enabled', true);

    (new DocumentRouter)->register(app(Registrar::class), [
        'prefix' => 'markdown-live',
        'name' => 'laradocs.',
        'middleware' => ['web'],
    ]);

    return 'markdown-live';
}

function enableVersionedDocs(string $unversioned = 'redirect'): void
{
    config()->set('laradocs.versions.enabled', true);
    config()->set('laradocs.versions.strategy', 'config');
    config()->set('laradocs.versions.default', 'v2');
    config()->set('laradocs.versions.unversioned', $unversioned);
    config()->set('laradocs.versions.available', [
        'v2' => 'v2.0',
        'v1' => 'v1.0',
    ]);
}

it('registers no markdown route until enabled', function () {
    expect(app('router')->getRoutes()->getByName('laradocs.markdown'))->toBeNull();

    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $this->get('/docs/a.md')->assertNotFound();
});

it('serves the page body as text/markdown', function () {
    $prefix = enableMarkdown();
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\n# Heading\n\nThe body of **A**.\n"]);

    $response = $this->get("/{$prefix}/a.md");

    $response->assertOk();
    expect((string) $response->headers->get('Content-Type'))->toStartWith('text/markdown')
        ->and($response->getContent())->toBe("# Heading\n\nThe body of **A**.\n");
});

it('interpolates variables the way llms-full.txt does', function () {
    $prefix = enableMarkdown();
    $this->app->make(VariableRegistry::class)->set('product', 'Acme');
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nWelcome to {{ product }}.\n"]);

    $body = $this->get("/{$prefix}/a.md")->getContent();

    expect($body)->toContain('Welcome to Acme.');
});

it('serves a section landing page from its _index.md', function () {
    $prefix = enableMarkdown();
    $this->makeDocs(['guide/_index.md' => "---\ntitle: Guide\n---\nThe guide overview.\n"]);

    $this->get("/{$prefix}/guide.md")
        ->assertOk()
        ->assertSee('The guide overview.', false);
});

it('404s hidden, redirecting and unknown pages', function () {
    $prefix = enableMarkdown();
    $this->makeDocs([
        'secret.md' => "---\ntitle: Secret\nhidden: true\n---\nShh.\n",
        'old.md' => "---\ntitle: Old\nredirect: secret\n---\n",
    ]);

    $this->get("/{$prefix}/secret.md")->assertNotFound();
    $this->get("/{$prefix}/old.md")->assertNotFound();
    $this->get("/{$prefix}/missing.md")->assertNotFound();
});

it('caches each page body in the laradocs cache', function () {
    config()->set('laradocs.cache.enabled', true);
    $prefix = enableMarkdown();
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $this->get("/{$prefix}/a.md")->assertOk();

    $document = app(Laradocs::class)->all()->findBySlug('a');

    expect(cache()->has(CacheKey::markdown($document)))->toBeTrue();
});

it('serves each version from its own folder', function () {
    enableVersionedDocs();
    $prefix = enableMarkdown();
    $this->makeDocs([
        'v1/intro.md' => "---\ntitle: Intro\n---\nOne body.\n",
        'v2/intro.md' => "---\ntitle: Intro\n---\nTwo body.\n",
    ]);

    $this->get("/{$prefix}/v1/intro.md")->assertSee('One body.', false);
    $this->get("/{$prefix}/v2/intro.md")->assertSee('Two body.', false);
});

it('renders the default version in place when unversioned requests are not redirected', function () {
    enableVersionedDocs('render');
    $prefix = enableMarkdown();
    $this->makeDocs([
        'v2/intro.md' => "---\ntitle: Intro\n---\nTwo body.\n",
    ]);

    $this->get("/{$prefix}/intro.md")->assertSee('Two body.', false);
});

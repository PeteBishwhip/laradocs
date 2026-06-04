<?php

declare(strict_types=1);

use Laradocs\Tests\Fixtures\FakeScoutEngine;
use Laravel\Scout\EngineManager;

/**
 * Point the package at a shared, in-memory Scout engine. EngineManager isn't a
 * shared singleton under Testbench, so we bind a pre-extended instance to make
 * sure the provider and the test observe the very same engine.
 */
function bindFakeScout(): FakeScoutEngine
{
    config()->set('laradocs.search.driver', 'scout');
    config()->set('scout.driver', 'fake');

    $fake = new FakeScoutEngine;
    $manager = new EngineManager(app());
    $manager->extend('fake', fn (): FakeScoutEngine => $fake);
    app()->instance(EngineManager::class, $manager);

    return $fake;
}

it('returns full-text matches with a url, group and excerpt', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n# Home\n",
        'guide/install.md' => "---\ntitle: Installation\ngroup: Guide\n---\nRun composer require to add the package to your app.\n",
    ]);

    $response = $this->getJson('/docs/_laradocs/search?q=composer')->assertOk();

    $response->assertJsonPath('results.0.slug', 'guide/install')
        ->assertJsonPath('results.0.title', 'Installation')
        ->assertJsonPath('results.0.group', 'Guide')
        ->assertJsonPath('results.0.url', url('/docs/guide/install'));

    expect($response->json('results.0.excerpt'))->toContain('composer');
});

it('builds an excerpt around the matched term with ellipses', function () {
    $body = str_repeat('lorem ipsum ', 40) . 'needle ' . str_repeat('dolor sit ', 40);

    $this->makeDocs([
        'long.md' => "---\ntitle: Long\n---\n{$body}\n",
    ]);

    $excerpt = $this->getJson('/docs/_laradocs/search?q=needle')->assertOk()->json('results.0.excerpt');

    expect($excerpt)->toContain('needle')
        ->and($excerpt)->toStartWith('…')
        ->and($excerpt)->toEndWith('…');
});

it('excerpts from the start without ellipses when the term leads short content', function () {
    $this->makeDocs([
        'lead.md' => "---\ntitle: Lead\n---\nneedlestart trailing words\n",
    ]);

    $excerpt = $this->getJson('/docs/_laradocs/search?q=needlestart')->assertOk()->json('results.0.excerpt');

    expect($excerpt)->toBe('needlestart trailing words');
});

it('falls back to a leading snippet when the match is in the title only', function () {
    $this->makeDocs([
        'titled.md' => "---\ntitle: Keyworded Heading\n---\nThis body never mentions the term itself.\n",
    ]);

    $excerpt = $this->getJson('/docs/_laradocs/search?q=keyworded')->assertOk()->json('results.0.excerpt');

    expect($excerpt)->toBe('This body never mentions the term itself.');
});

it('returns an empty excerpt for a body-less page matched by title', function () {
    $this->makeDocs([
        'blank.md' => "---\ntitle: Emptyword\n---\n",
    ]);

    $this->getJson('/docs/_laradocs/search?q=emptyword')
        ->assertOk()
        ->assertJsonPath('results.0.slug', 'blank')
        ->assertJsonPath('results.0.excerpt', '');
});

it('returns nothing for queries shorter than the minimum', function () {
    $this->makeDocs(['a.md' => "---\ntitle: Alpha\n---\nbody\n"]);

    $this->getJson('/docs/_laradocs/search?q=a')
        ->assertOk()
        ->assertExactJson(['results' => []]);
});

it('ignores a non-string query parameter', function () {
    $this->makeDocs(['a.md' => "---\ntitle: Alpha\n---\nbody\n"]);

    $this->getJson('/docs/_laradocs/search?q[]=x')
        ->assertOk()
        ->assertExactJson(['results' => []]);
});

it('404s the search endpoint when search is disabled', function () {
    config()->set('laradocs.ui.search.enabled', false);
    $this->makeDocs(['a.md' => "---\ntitle: Alpha\n---\nbody\n"]);

    $this->getJson('/docs/_laradocs/search?q=alpha')->assertNotFound();
});

it('laradocs:index reports the indexed page count and engine', function () {
    $this->makeDocs(['a.md' => "---\ntitle: Alpha\n---\nbody\n", 'b.md' => "---\ntitle: Beta\n---\nbody\n"]);

    $this->artisan('laradocs:index')
        ->expectsOutputToContain('Indexed 2 page(s) for search (json engine).')
        ->assertSuccessful();
});

it('laradocs:cache also rebuilds the search index', function () {
    config()->set('laradocs.cache.enabled', true);
    config()->set('laradocs.cache.store', 'array');
    $this->makeDocs(['a.md' => "---\ntitle: Alpha\n---\nbody\n"]);

    $this->artisan('laradocs:cache')
        ->expectsOutputToContain('Cached 1')
        ->expectsOutputToContain('Indexed 1')
        ->assertSuccessful();
});

it('laradocs:clear flushes the search engine', function () {
    $fake = bindFakeScout();
    $this->makeDocs(['a.md' => "---\ntitle: Alpha\n---\nbody\n"]);

    $this->artisan('laradocs:clear')->assertSuccessful();

    expect($fake->flushed)->toBeGreaterThan(0);
});

it('searches through the configured scout engine end to end', function () {
    bindFakeScout();
    $this->makeDocs([
        'guide/install.md' => "---\ntitle: Installation\ngroup: Guide\n---\nRun composer require to install.\n",
        'guide/config.md' => "---\ntitle: Configuration\ngroup: Guide\n---\nPublish the config file.\n",
    ]);

    $this->artisan('laradocs:index')
        ->expectsOutputToContain('scout engine')
        ->assertSuccessful();

    $this->getJson('/docs/_laradocs/search?q=composer')
        ->assertOk()
        ->assertJsonPath('results.0.slug', 'guide/install');
});

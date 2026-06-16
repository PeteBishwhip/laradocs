<?php

declare(strict_types=1);

use Laradocs\Contracts\DocumentLoader;

/**
 * Per-language documentation *content* — distinct from the interface-string
 * localisation covered by LocalisationTest. A page may ship a translation as a
 * filename suffix (guide.fr.md) or a locale directory (fr/guide.md); the loader
 * serves the request's locale and falls back to the default when a translation
 * is missing.
 */
beforeEach(function () {
    // Recognise these as content locales without depending on published lang
    // directories on disk (an explicit array short-circuits the scan).
    config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français', 'de' => 'Deutsch']);
    config()->set('laradocs.locale.default', 'en');
    app()->setLocale('en');
});

it('serves a per-language page via the filename suffix convention', function () {
    $this->makeDocs([
        'index.md' => "---\ntitle: Home\norder: 1\n---\n# Welcome\n",
        'guide.md' => "---\ntitle: Guide\n---\n# Guide\n\nEnglish body.\n",
        'guide.fr.md' => "---\ntitle: Guide\n---\n# Guide\n\nCorps français.\n",
    ]);

    $this->get('/docs/guide')->assertOk()->assertSee('English body.');
    $this->get('/docs/guide?lang=fr')
        ->assertOk()
        ->assertSee('Corps français.')
        ->assertDontSee('English body.');
});

it('serves a per-language page via the directory convention', function () {
    $this->makeDocs([
        'guide.md' => "---\ntitle: Guide\n---\n# Guide\n\nEnglish body.\n",
        'de/guide.md' => "---\ntitle: Anleitung\n---\n# Guide\n\nDeutscher Text.\n",
    ]);

    $this->get('/docs/guide')->assertOk()->assertSee('English body.');
    $this->get('/docs/guide?lang=de')
        ->assertOk()
        ->assertSee('Deutscher Text.')
        ->assertDontSee('English body.');
});

it('falls back to the default-locale page when a translation is missing', function () {
    $this->makeDocs([
        'guide.md' => "---\ntitle: Guide\n---\n# Guide\n\nEnglish body.\n",
        'guide.fr.md' => "---\ntitle: Guide\n---\n# Guide\n\nCorps français.\n",
        'about.md' => "---\ntitle: About\n---\n# About\n\nUntranslated.\n",
    ]);

    // "about" has no French translation, so a French request still resolves to
    // the English source rather than 404ing.
    $this->get('/docs/about?lang=fr')->assertOk()->assertSee('Untranslated.');
});

it('serves a translation that lives under the default-locale page on its shared slug', function () {
    $this->makeDocs([
        'guide.md' => "---\ntitle: Guide\n---\nEN\n",
        'guide.fr.md' => "---\ntitle: Guide\n---\nFR\n",
    ]);

    $loader = app(DocumentLoader::class);

    // A page and its translation collapse to a single slug, so the public URL
    // is stable across languages.
    expect($loader->all())->toHaveCount(1)
        ->and($loader->all()->first()->slug)->toBe('guide');
});

it('tags an un-suffixed page with the default content locale', function () {
    $this->makeDocs([
        'guide.md' => "---\ntitle: Guide\n---\nbody\n",
    ]);

    expect(app(DocumentLoader::class)->all()->first()->locale)->toBe('en');
});

it('tags a suffixed page with its detected locale', function () {
    app()->setLocale('fr');

    $this->makeDocs([
        'guide.fr.md' => "---\ntitle: Guide\n---\nbody\n",
    ]);

    $doc = app(DocumentLoader::class)->find('guide');

    expect($doc)->not->toBeNull()
        ->and($doc->locale)->toBe('fr');
});

it('does not mistake an ordinary dotted filename for a translation', function () {
    $this->makeDocs([
        // "example" is not a recognised locale, so the dot is left in the slug.
        'config.example.md' => "---\ntitle: Example\n---\nbody\n",
    ]);

    $doc = app(DocumentLoader::class)->all()->first();

    expect($doc->slug)->toBe('configexample')
        ->and($doc->locale)->toBe('en');
});

it('does not mistake a directory that merely shares a non-locale name', function () {
    $this->makeDocs([
        // "guides" is not a locale code; the segment stays in the slug.
        'guides/intro.md' => "---\ntitle: Intro\n---\nbody\n",
    ]);

    expect(app(DocumentLoader::class)->all()->first()->slug)->toBe('guides/intro');
});

it('leaves content untouched when no content locales are recognised', function () {
    // An empty available array opts out of localisation entirely.
    config()->set('laradocs.locale.available', []);

    $this->makeDocs([
        'guide.md' => "---\ntitle: Guide\n---\nEN\n",
        'guide.fr.md' => "---\ntitle: Guide FR\n---\nFR\n",
    ]);

    // With localisation off, the suffix is treated as a literal slug rather
    // than a translation, so both files load as distinct pages.
    $loader = app(DocumentLoader::class);

    expect($loader->all())->toHaveCount(2)
        ->and($loader->all()->pluck('locale')->unique()->all())->toBe([null]);
});

it('localises the navigation tree and home page for the active locale', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n# Welcome\n\nEnglish home.\n",
        '_index.fr.md' => "---\ntitle: Accueil\n---\n# Accueil\n\nAccueil français.\n",
    ]);

    $this->get('/docs')->assertOk()->assertSee('English home.');
    $this->get('/docs?lang=fr')->assertOk()->assertSee('Accueil français.');
});

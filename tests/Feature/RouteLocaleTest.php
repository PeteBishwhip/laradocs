<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * Route-based locales: the language lives in the URL path (/docs/fr/guide)
 * rather than a ?lang= query, giving each locale a canonical, crawlable URL.
 * Covers routing, the back-compat redirect shim, default-locale canonicalisation
 * and the per-locale SEO tags. Content/interface localisation themselves are
 * covered by ContentLocalisationTest and LocalisationTest.
 */
beforeEach(function () {
    config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français']);
    config()->set('laradocs.locale.default', 'en');

    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\norder: 1\n---\n# Welcome home\n",
        'guide/getting-started.md' => "---\ntitle: Getting Started\norder: 1\n---\n## Step one\n",
    ]);
});

// ---------------------------------------------------------------------------
// Routing
// ---------------------------------------------------------------------------

it('renders a doc page under a non-default locale segment', function () {
    $this->get('/docs/fr/guide/getting-started')
        ->assertOk()
        ->assertSee('Step one');
});

it('renders the locale landing page at the bare locale segment', function () {
    $this->get('/docs/fr')->assertOk()->assertSee('Welcome home');
});

it('serves the default locale unprefixed', function () {
    $this->get('/docs/guide/getting-started')->assertOk()->assertSee('Step one');
});

it('treats an unknown locale code as an ordinary slug and 404s', function () {
    // "zz" is not an available locale, so it is not stripped — it becomes part
    // of the slug, which matches no document.
    $this->get('/docs/zz/guide/getting-started')->assertNotFound();
});

// ---------------------------------------------------------------------------
// Redirects
// ---------------------------------------------------------------------------

it('301-redirects a legacy ?lang= query to the path form', function () {
    $this->get('/docs/guide/getting-started?lang=fr')
        ->assertStatus(301)
        ->assertRedirect('/docs/fr/guide/getting-started');
});

it('301-redirects ?lang= on the index to the locale landing page', function () {
    $this->get('/docs?lang=fr')->assertStatus(301)->assertRedirect('/docs/fr');
});

it('drops ?lang= for the default locale, redirecting to the unprefixed form', function () {
    $this->get('/docs/guide/getting-started?lang=en')
        ->assertStatus(301)
        ->assertRedirect('/docs/guide/getting-started');
});

it('preserves other query parameters when redirecting a ?lang= request', function () {
    $this->get('/docs/guide/getting-started?lang=fr&foo=bar')
        ->assertStatus(301)
        ->assertRedirect('/docs/fr/guide/getting-started?foo=bar');
});

it('301-redirects a default-locale prefix to the canonical unprefixed URL', function () {
    $this->get('/docs/en/guide/getting-started')
        ->assertStatus(301)
        ->assertRedirect('/docs/guide/getting-started');
});

it('canonicalises a bare default-locale segment to the docs root', function () {
    $this->get('/docs/en')->assertStatus(301)->assertRedirect('/docs');
});

it('ignores an unknown ?lang= code without redirecting', function () {
    // "zz" is not available, so there is nothing to redirect to — the page
    // renders in the default locale as if the query were absent.
    $this->get('/docs/guide/getting-started?lang=zz')->assertOk()->assertSee('Step one');
});

// ---------------------------------------------------------------------------
// SEO tags
// ---------------------------------------------------------------------------

it('emits per-locale hreflang alternates including x-default', function () {
    $this->get('/docs/fr/guide/getting-started')
        ->assertOk()
        ->assertSee('<link rel="alternate" hreflang="en" href="' . url('/docs/guide/getting-started') . '">', false)
        ->assertSee('<link rel="alternate" hreflang="fr" href="' . url('/docs/fr/guide/getting-started') . '">', false)
        ->assertSee('<link rel="alternate" hreflang="x-default" href="' . url('/docs/guide/getting-started') . '">', false);
});

it('emits a per-locale canonical pointing at the active locale URL', function () {
    $this->get('/docs/fr/guide/getting-started')
        ->assertOk()
        ->assertSee('rel="canonical" href="' . url('/docs/fr/guide/getting-started') . '"', false);
});

// ---------------------------------------------------------------------------
// Internal links stay within the active locale
// ---------------------------------------------------------------------------

it('keeps internal navigation links within the active locale', function () {
    $html = $this->get('/docs/fr/guide/getting-started')->assertOk()->getContent();

    // The sidebar links to sibling pages must carry the /fr/ segment.
    expect($html)->toContain(url('/docs/fr/guide/getting-started'));
});

it('points the language selector at the same page in each locale', function () {
    $this->get('/docs/fr/guide/getting-started')
        ->assertOk()
        // The selector switches to the same page: English (default) unprefixed,
        // French prefixed — confirming the active slug reaches the partial.
        ->assertSee('href="' . url('/docs/guide/getting-started') . '"', false)
        ->assertSee('href="' . url('/docs/fr/guide/getting-started') . '"', false);
});

// ---------------------------------------------------------------------------
// Legacy mode (locale.url = false)
// ---------------------------------------------------------------------------

it('does not redirect ?lang= when URL-path locales are disabled', function () {
    config()->set('laradocs.locale.url', false);

    File::ensureDirectoryExists(lang_path('vendor/laradocs/fr'));
    File::put(
        lang_path('vendor/laradocs/fr/laradocs.php'),
        "<?php\n\nreturn ['search' => ['trigger' => 'Rechercher dans la doc...']];\n",
    );

    try {
        // In legacy mode the query selects the locale in place, no redirect.
        $this->get('/docs/guide/getting-started?lang=fr')
            ->assertOk()
            ->assertSee('Rechercher dans la doc...');
    } finally {
        File::deleteDirectory(lang_path('vendor/laradocs/fr'));
    }
});

it('writes the persistence cookie in place for a legacy ?lang= request', function () {
    config()->set('laradocs.locale.url', false);
    config()->set('laradocs.locale.cookie', true);

    // With URL locales off the ?lang= choice is applied to the rendered page
    // (no redirect), so the persistence cookie is written on that 200 response
    // rather than riding along on a 301.
    $this->get('/docs/guide/getting-started?lang=fr')
        ->assertOk()
        ->assertCookie('laradocs_locale', 'fr');
});

it('does not register locale routes for a single-locale site', function () {
    config()->set('laradocs.locale.available', ['en' => 'English']);

    // With only one locale there is nothing to prefix, so /docs/en is just a
    // missing slug, and ?lang= has no path form to redirect to.
    $this->get('/docs/en')->assertNotFound();
    $this->get('/docs/guide/getting-started?lang=en')->assertOk()->assertSee('Step one');
});

// ---------------------------------------------------------------------------
// Locale composed with versions: /docs/{locale}/{version}/{slug}
// ---------------------------------------------------------------------------

describe('locale composed with versions', function () {
    beforeEach(function () {
        config()->set('laradocs.versions.enabled', true);
        config()->set('laradocs.versions.strategy', 'config');
        config()->set('laradocs.versions.available', ['v1.0' => 'v1.0', 'v2.0' => 'v2.0']);
        config()->set('laradocs.versions.default', 'v2.0');
        config()->set('laradocs.versions.unversioned', 'redirect');

        $this->makeDocs([
            'v2.0/guide.md' => "---\ntitle: Guide\n---\n# Guide\n\nEnglish v2 body.\n",
            'v2.0/guide.fr.md' => "---\ntitle: Guide\n---\n# Guide\n\nCorps v2 français.\n",
        ]);
    });

    it('renders a versioned page under the locale segment', function () {
        $this->get('/docs/fr/v2.0/guide')
            ->assertOk()
            ->assertSee('Corps v2 français.')
            ->assertDontSee('English v2 body.');
    });

    it('redirects a versioned ?lang= request into the {locale}/{version} path form', function () {
        $this->get('/docs/v2.0/guide?lang=fr')
            ->assertStatus(301)
            ->assertRedirect('/docs/fr/v2.0/guide');
    });

    it('keeps both the locale and version segments on internal links', function () {
        $html = $this->get('/docs/fr/v2.0/guide')->assertOk()->getContent();

        expect($html)->toContain(url('/docs/fr/v2.0/guide'));
    });
});

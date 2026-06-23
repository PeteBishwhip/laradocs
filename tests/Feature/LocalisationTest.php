<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Laradocs\Http\Middleware\SetDocsLocale;
use Laradocs\Laradocs;
use Laradocs\Support\Locale;

/**
 * Strip everything from a Blade template that legitimately contains
 * non-translatable text — comments, PHP/echo blocks, directives, scripts,
 * styles, inline SVG and keyboard hints — leaving only the literal text and
 * attributes that an author might forget to wrap in __().
 */
function laradocsVisibleText(string $blade): string
{
    $patterns = [
        '/\{\{--.*?--\}\}/s',                 // {{-- blade comments --}}
        '/@php\b.*?@endphp/s',                 // @php ... @endphp blocks
        '/@verbatim\b.*?@endverbatim/s',       // raw blocks
        '/\{!!.*?!!\}/s',                      // {!! unescaped echoes !!}
        '/\{\{.*?\}\}/s',                      // {{ echoes }}
        '/<script\b.*?<\/script>/is',          // inline JS
        '/<style\b.*?<\/style>/is',            // inline CSS
        '/<svg\b.*?<\/svg>/is',                // icon paths
        '/<kbd\b.*?<\/kbd>/is',                // keyboard key hints
        '/<!--.*?-->/s',                       // <!-- html comments -->
        // Blade directives, including balanced (possibly nested) parens.
        '/@\w+\s*(\((?:[^()]|(?1))*\))?/',
    ];

    foreach ($patterns as $pattern) {
        $blade = (string) preg_replace($pattern, ' ', $blade);
    }

    return $blade;
}

it('wraps every user-facing string in the bundled views in a translation call', function () {
    $views = File::allFiles(__DIR__ . '/../../resources/views');

    $offenders = [];

    foreach ($views as $view) {
        $stripped = laradocsVisibleText($view->getContents());

        // Hardcoded text inside translatable attributes. aria-labelledby is
        // intentionally excluded — it references an element id, not a string.
        preg_match_all(
            '/\b(?:aria-label|placeholder|alt|title)\s*=\s*"([^"]*)"/i',
            $stripped,
            $attrMatches,
        );

        foreach ($attrMatches[1] as $value) {
            if (preg_match('/[A-Za-z]{2,}/', $value)) {
                $offenders[] = $view->getRelativePathname() . ': attribute "' . trim($value) . '"';
            }
        }

        // Visible text nodes: drop every remaining tag, then look at what's
        // left between them. Anything with a real word should have been
        // wrapped in __() (which the echo-stripping above would have removed).
        $text = (string) preg_replace('/<[^>]+>/s', ' ', $stripped);

        foreach (preg_split('/\s+/', $text) ?: [] as $word) {
            if (preg_match('/[A-Za-z]{2,}/', $word)) {
                $offenders[] = $view->getRelativePathname() . ': text "' . $word . '"';
            }
        }
    }

    expect($offenders)->toBe(
        [],
        "Found user-facing strings that are not wrapped in __():\n" . implode("\n", $offenders),
    );
});

it('defines an English translation for every key referenced by the views', function () {
    $views = File::allFiles(__DIR__ . '/../../resources/views');

    $keys = [];

    foreach ($views as $view) {
        preg_match_all(
            "/__\(\s*'(laradocs::laradocs\.[^']+)'/",
            $view->getContents(),
            $matches,
        );

        $keys = array_merge($keys, $matches[1]);
    }

    $keys = array_values(array_unique($keys));

    expect($keys)->not->toBeEmpty();

    foreach ($keys as $key) {
        expect(trans($key))->not->toBe($key, "Missing translation for {$key}");
    }
});

it('registers a publishable language tag', function () {
    expect(array_keys(ServiceProvider::$publishGroups))
        ->toContain('laradocs-lang');
});

it('exposes the language files under the laradocs namespace', function () {
    expect(trans('laradocs::laradocs.nav.home'))->toBe('Home')
        ->and(trans('laradocs::laradocs.page.last_updated', ['date' => '2025']))
        ->toBe('Last updated 2025');
});

it('falls back to the application locale by default', function () {
    config()->set('app.locale', 'en');
    config()->set('laradocs.locale.default', null);
    config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français']);

    expect(Locale::fallback())->toBe('en');
});

it('uses an explicit configured default locale when set', function () {
    config()->set('laradocs.locale.default', 'fr');
    config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français']);

    expect(Locale::fallback())->toBe('fr');
});

it('falls back to the first available locale when the app locale is unknown', function () {
    config()->set('app.locale', 'zz');
    config()->set('laradocs.locale.default', null);
    config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français']);

    expect(Locale::fallback())->toBe('en');
});

it('honours a valid ?lang query parameter', function () {
    config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français']);

    $request = Request::create('/docs?lang=fr');

    expect(Locale::determine($request))->toBe('fr');
});

it('splits a path into locale and remainder, leaving it untouched when URL locales are disabled', function () {
    config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français']);

    // With URL locales on, a leading available-locale segment is peeled off.
    config()->set('laradocs.locale.url', true);
    expect(Locale::split('fr/guide/intro'))->toBe(['fr', 'guide/intro']);

    // With URL locales off, split is a no-op — the path is returned verbatim so
    // a real slug that happens to look like a locale keeps working.
    config()->set('laradocs.locale.url', false);
    expect(Locale::split('fr/guide/intro'))->toBe([null, 'fr/guide/intro']);
});

it('falls through to the query/cookie/browser chain when the request has no bound route', function () {
    config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français']);
    config()->set('laradocs.locale.url', true);

    // Invoked directly with a route-less request (as a defensive guard for any
    // catch-all that reaches the middleware before route binding): there is no
    // path to strip, so resolution falls back to Locale::determine() — here the
    // ?lang= query selects French.
    $request = Request::create('/docs?lang=fr');

    $response = (new SetDocsLocale)->handle(
        $request,
        fn () => response((string) app()->getLocale()),
    );

    expect($response->getContent())->toBe('fr');
});

it('ignores an unknown ?lang query parameter', function () {
    config()->set('laradocs.locale.available', ['en' => 'English']);
    config()->set('laradocs.locale.default', 'en');

    $request = Request::create('/docs?lang=zz');

    expect(Locale::determine($request))->toBe('en');
});

it('remembers a language choice from the cookie when locale.cookie is enabled', function () {
    config()->set('laradocs.locale.available', ['en' => 'English', 'de' => 'Deutsch']);
    config()->set('laradocs.locale.cookie', true);

    $request = Request::create('/docs');
    $request->cookies->set('laradocs_locale', 'de');

    expect(Locale::determine($request))->toBe('de');
});

it('ignores the cookie when locale.cookie is disabled', function () {
    config()->set('laradocs.locale.available', ['en' => 'English', 'de' => 'Deutsch']);
    config()->set('laradocs.locale.cookie', false);
    config()->set('laradocs.locale.default', 'en');
    config()->set('laradocs.locale.detect_browser', false);

    $request = Request::create('/docs');
    $request->cookies->set('laradocs_locale', 'de');

    expect(Locale::determine($request))->toBe('en');
});

it('renders the language selector only when more than one locale is offered', function () {
    $this->makeDocs([
        'index.md' => "---\ntitle: Home\norder: 1\n---\n# Welcome\n\nSome content here.\n",
    ]);

    config()->set('laradocs.locale.available', ['en' => 'English']);
    $this->get('/docs')->assertOk()->assertDontSee('data-laradocs-lang', false);

    config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français']);
    $this->get('/docs')
        ->assertOk()
        ->assertSee('data-laradocs-lang', false)
        ->assertSee('Français');
});

it('applies the requested locale to the rendered docs page', function () {
    File::ensureDirectoryExists(lang_path('vendor/laradocs/fr'));
    File::put(
        lang_path('vendor/laradocs/fr/laradocs.php'),
        "<?php\n\nreturn ['search' => ['trigger' => 'Rechercher dans la doc...']];\n",
    );

    try {
        $this->makeDocs([
            'index.md' => "---\ntitle: Home\norder: 1\n---\n# Welcome\n",
            'guide.md' => "---\ntitle: Guide\n---\n## Guide\n\nContent.\n",
        ]);

        config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français']);

        // The header search trigger renders on every page. Without a prefix it
        // stays English; the /fr/ path segment resolves through the middleware
        // to French, and the legacy ?lang=fr 301-redirects to that path form.
        $this->get('/docs/guide')->assertOk()->assertSee('Search the docs...');
        $this->get('/docs/fr/guide')->assertOk()->assertSee('Rechercher dans la doc...');
        $this->get('/docs/guide?lang=fr')->assertRedirect('/docs/fr/guide');
    } finally {
        File::deleteDirectory(lang_path('vendor/laradocs/fr'));
    }
});

it('auto-detects locales from lang/vendor/laradocs/ when available is null', function () {
    config()->set('laradocs.locale.available', null);

    File::ensureDirectoryExists(lang_path('vendor/laradocs/en'));
    File::ensureDirectoryExists(lang_path('vendor/laradocs/fr'));

    try {
        $locales = Locale::available();

        expect($locales)->toHaveKey('en')->toHaveKey('fr');
    } finally {
        File::deleteDirectory(lang_path('vendor/laradocs/en'));
        File::deleteDirectory(lang_path('vendor/laradocs/fr'));
    }
});

it('uses the locale code as the label when no meta.php is present', function () {
    config()->set('laradocs.locale.available', null);

    File::ensureDirectoryExists(lang_path('vendor/laradocs/de'));

    try {
        $locales = Locale::available();

        expect($locales['de'])->toBe('de');
    } finally {
        File::deleteDirectory(lang_path('vendor/laradocs/de'));
    }
});

it('reads the label from meta.php when present', function () {
    config()->set('laradocs.locale.available', null);

    File::ensureDirectoryExists(lang_path('vendor/laradocs/fr'));
    File::put(lang_path('vendor/laradocs/fr/meta.php'), "<?php\n\nreturn ['label' => 'Français'];\n");

    try {
        $locales = Locale::available();

        expect($locales['fr'])->toBe('Français');
    } finally {
        File::deleteDirectory(lang_path('vendor/laradocs/fr'));
    }
});

it('returns an empty array when the lang directory does not exist', function () {
    config()->set('laradocs.locale.available', null);

    expect(Locale::available())->toBe([]);
});

it('ignores files in the lang directory — only directories count as locales', function () {
    config()->set('laradocs.locale.available', null);

    File::ensureDirectoryExists(lang_path('vendor/laradocs'));
    File::put(lang_path('vendor/laradocs/README.md'), '# Lang');
    File::ensureDirectoryExists(lang_path('vendor/laradocs/en'));

    try {
        $locales = Locale::available();

        expect($locales)->toHaveKey('en')->not->toHaveKey('README.md');
    } finally {
        File::deleteDirectory(lang_path('vendor/laradocs'));
    }
});

it('respects a non-empty available array as an explicit override over auto-detection', function () {
    config()->set('laradocs.locale.available', ['en' => 'English']);

    // Even if dirs exist on disk, the explicit config wins.
    File::ensureDirectoryExists(lang_path('vendor/laradocs/fr'));

    try {
        expect(Locale::available())->toBe(['en' => 'English']);
    } finally {
        File::deleteDirectory(lang_path('vendor/laradocs/fr'));
    }
});

it('treats an empty available array as a deliberate opt-out, skipping auto-detection', function () {
    config()->set('laradocs.locale.available', []);

    // Locale directories exist on disk, but the empty array disables them.
    File::ensureDirectoryExists(lang_path('vendor/laradocs/en'));
    File::ensureDirectoryExists(lang_path('vendor/laradocs/fr'));

    try {
        expect(Locale::available())->toBe([]);
    } finally {
        File::deleteDirectory(lang_path('vendor/laradocs/en'));
        File::deleteDirectory(lang_path('vendor/laradocs/fr'));
    }
});

it('caches the auto-detected locales so the filesystem is only scanned once', function () {
    config()->set('laradocs.locale.available', null);
    config()->set('laradocs.cache.enabled', true);

    $key = config('laradocs.cache.key_prefix', 'laradocs') . ':locales';
    cache()->forget($key);

    File::ensureDirectoryExists(lang_path('vendor/laradocs/en'));
    File::ensureDirectoryExists(lang_path('vendor/laradocs/fr'));

    try {
        // First call scans the filesystem and primes the cache.
        expect(Locale::available())->toHaveKey('fr');

        // Removing the directory afterwards has no effect: the cached value is
        // served without touching the filesystem again.
        File::deleteDirectory(lang_path('vendor/laradocs/fr'));

        expect(Locale::available())->toHaveKey('fr')
            ->and(cache()->get($key))->toHaveKey('fr');
    } finally {
        File::deleteDirectory(lang_path('vendor/laradocs/en'));
        File::deleteDirectory(lang_path('vendor/laradocs/fr'));
        cache()->forget($key);
    }
});

// ---------------------------------------------------------------------------
// Cookie persistence — round-trip
// ---------------------------------------------------------------------------

it('sets the laradocs_locale cookie in the response when locale.cookie is enabled and an explicit choice is made', function () {
    $this->makeDocs([
        'index.md' => "---\ntitle: Home\norder: 1\n---\n# Welcome\n",
    ]);

    config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français']);
    config()->set('laradocs.locale.cookie', true);

    // The legacy ?lang= now 301s to the path form, and the persistence cookie
    // rides along on the redirect so the choice survives even for clients that
    // strip the query before following.
    $response = $this->get('/docs?lang=fr')->assertRedirect('/docs/fr');

    $response->assertCookie('laradocs_locale', 'fr');
});

it('does not set a cookie at all when locale.cookie is disabled (default)', function () {
    $this->makeDocs([
        'index.md' => "---\ntitle: Home\norder: 1\n---\n# Welcome\n",
    ]);

    config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français']);
    config()->set('laradocs.locale.cookie', false);

    // Even an explicit ?lang= choice must not write a cookie when the flag is
    // off, so EU deployments don't need a consent banner just for the switcher.
    // The query still 301s to the path form; it simply carries no cookie.
    $this->get('/docs?lang=fr')->assertRedirect('/docs/fr')->assertCookieMissing('laradocs_locale');
});

it('uses the cookie to serve the chosen language on a follow-up request without ?lang= when locale.cookie is enabled', function () {
    File::ensureDirectoryExists(lang_path('vendor/laradocs/fr'));
    File::put(
        lang_path('vendor/laradocs/fr/laradocs.php'),
        "<?php\n\nreturn ['search' => ['trigger' => 'Rechercher dans la doc...']];\n",
    );

    try {
        $this->makeDocs([
            'index.md' => "---\ntitle: Home\norder: 1\n---\n# Welcome\n",
        ]);

        config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français']);
        config()->set('laradocs.locale.cookie', true);

        // First visit with an explicit choice plants the cookie.
        $this->get('/docs?lang=fr');

        // Second visit carries the cookie but no query parameter — the language
        // should still be French because the middleware reads the cookie.
        $this->withCookies(['laradocs_locale' => 'fr'])
            ->get('/docs')
            ->assertOk()
            ->assertSee('Rechercher dans la doc...');
    } finally {
        File::deleteDirectory(lang_path('vendor/laradocs/fr'));
    }
});

it('does not set the persistence cookie when no explicit choice is made', function () {
    $this->makeDocs([
        'index.md' => "---\ntitle: Home\norder: 1\n---\n# Welcome\n",
    ]);

    config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français']);
    config()->set('laradocs.locale.cookie', true);

    // Browsing without picking a language must not set the cookie, so that a
    // future explicit choice is not shadowed by an accidental persistence.
    $this->get('/docs')->assertOk()->assertCookieMissing('laradocs_locale');
});

// ---------------------------------------------------------------------------
// Accept-Language browser detection
// ---------------------------------------------------------------------------

it('selects a locale from the Accept-Language header when no explicit choice has been made', function () {
    File::ensureDirectoryExists(lang_path('vendor/laradocs/fr'));
    File::put(
        lang_path('vendor/laradocs/fr/laradocs.php'),
        "<?php\n\nreturn ['search' => ['trigger' => 'Rechercher dans la doc...']];\n",
    );

    try {
        $this->makeDocs([
            'index.md' => "---\ntitle: Home\norder: 1\n---\n# Welcome\n",
        ]);

        config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français']);

        $this->withHeaders(['Accept-Language' => 'fr'])
            ->get('/docs')
            ->assertOk()
            ->assertSee('Rechercher dans la doc...');
    } finally {
        File::deleteDirectory(lang_path('vendor/laradocs/fr'));
    }
});

it('respects quality weights in the Accept-Language header', function () {
    config()->set('laradocs.locale.available', ['en' => 'English', 'de' => 'Deutsch', 'fr' => 'Français']);

    // de;q=0.9 is preferred over fr;q=0.7, so de wins.
    $request = Request::create('/docs', 'GET', [], [], [], ['HTTP_ACCEPT_LANGUAGE' => 'fr;q=0.7, de;q=0.9']);

    expect(Locale::determine($request))->toBe('de');
});

it('falls back to the primary language subtag when the full region tag is unavailable', function () {
    config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français']);

    // The browser sends "fr-CA" but we only have "fr"; the primary subtag match
    // should resolve to the available "fr" locale.
    $request = Request::create('/docs', 'GET', [], [], [], ['HTTP_ACCEPT_LANGUAGE' => 'fr-CA']);

    expect(Locale::determine($request))->toBe('fr');
});

it('ignores Accept-Language locales not present in the available list', function () {
    config()->set('laradocs.locale.available', ['en' => 'English']);
    config()->set('laradocs.locale.default', 'en');

    $request = Request::create('/docs', 'GET', [], [], [], ['HTTP_ACCEPT_LANGUAGE' => 'ja, zh-TW']);

    // None of the browser locales are available — falls back to the default.
    expect(Locale::determine($request))->toBe('en');
});

it('does not use Accept-Language when detect_browser is disabled', function () {
    config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français']);
    config()->set('laradocs.locale.default', 'en');
    config()->set('laradocs.locale.detect_browser', false);

    $request = Request::create('/docs', 'GET', [], [], [], ['HTTP_ACCEPT_LANGUAGE' => 'fr']);

    expect(Locale::determine($request))->toBe('en');
});

it('explicit ?lang= wins over Accept-Language', function () {
    config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français', 'de' => 'Deutsch']);

    // Browser prefers fr, but the visitor explicitly chose de — de wins.
    $request = Request::create('/docs?lang=de', 'GET', [], [], [], ['HTTP_ACCEPT_LANGUAGE' => 'fr']);

    expect(Locale::determine($request))->toBe('de');
});

it('cookie wins over Accept-Language when locale.cookie is enabled', function () {
    config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français', 'de' => 'Deutsch']);
    config()->set('laradocs.locale.cookie', true);

    // Browser prefers fr, but the visitor's cookie says de — de wins.
    $request = Request::create('/docs', 'GET', [], [], [], ['HTTP_ACCEPT_LANGUAGE' => 'fr']);
    $request->cookies->set('laradocs_locale', 'de');

    expect(Locale::determine($request))->toBe('de');
});

it('fromAcceptLanguage returns null for an empty header', function () {
    expect(Locale::fromAcceptLanguage('', ['en' => 'English']))->toBeNull();
});

it('fromAcceptLanguage returns null when no header locale matches the available list', function () {
    expect(Locale::fromAcceptLanguage('ja, zh', ['en' => 'English']))->toBeNull();
});

it('fromAcceptLanguage skips empty segments in the header', function () {
    // A header with stray/trailing commas yields empty segments that must be
    // ignored rather than treated as a (blank) language tag.
    expect(Locale::fromAcceptLanguage('fr, , en', ['en' => 'English']))->toBe('en')
        ->and(Locale::fromAcceptLanguage(',,', ['en' => 'English']))->toBeNull();
});

// ---------------------------------------------------------------------------
// Worker safety (Octane)
// ---------------------------------------------------------------------------
// cookiesEnabled() callback
// ---------------------------------------------------------------------------

it('Laradocs::cookiesEnabled registers a callback that enables cookie reading regardless of config', function () {
    config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français']);
    config()->set('laradocs.locale.cookie', false); // config says off

    // Register through the public fluent API (not Locale::setCookieResolver
    // directly) so the entry point developers actually use is covered.
    $laradocs = app(Laradocs::class);
    expect($laradocs->cookiesEnabled(fn () => true))->toBe($laradocs); // callback overrides, fluent

    try {
        $request = Request::create('/docs', 'GET');
        $request->cookies->set('laradocs_locale', 'fr');

        expect(Locale::determine($request))->toBe('fr');
    } finally {
        Locale::setCookieResolver(null);
    }
});

it('cookiesEnabled callback returning false disables cookies even when locale.cookie config is true', function () {
    config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français']);
    config()->set('laradocs.locale.cookie', true); // config says on
    config()->set('laradocs.locale.detect_browser', false);

    Locale::setCookieResolver(fn () => false); // callback overrides

    try {
        $request = Request::create('/docs', 'GET');
        $request->cookies->set('laradocs_locale', 'fr');

        expect(Locale::determine($request))->toBe('en'); // cookie ignored → fallback
    } finally {
        Locale::setCookieResolver(null);
    }
});

it('clearing the cookiesEnabled callback reverts to the locale.cookie config value', function () {
    config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français']);
    config()->set('laradocs.locale.cookie', false);
    config()->set('laradocs.locale.detect_browser', false);

    Locale::setCookieResolver(fn () => true);
    Locale::setCookieResolver(null); // clear — should fall back to config (false)

    $request = Request::create('/docs', 'GET');
    $request->cookies->set('laradocs_locale', 'fr');

    expect(Locale::determine($request))->toBe('en'); // cookie ignored because config=false
});

// ---------------------------------------------------------------------------

it('restores the application locale after a docs request so workers do not leak (octane-safe)', function () {
    File::ensureDirectoryExists(lang_path('vendor/laradocs/fr'));
    File::put(
        lang_path('vendor/laradocs/fr/laradocs.php'),
        "<?php\n\nreturn ['search' => ['trigger' => 'Rechercher dans la doc...']];\n",
    );

    try {
        $this->makeDocs([
            'index.md' => "---\ntitle: Home\norder: 1\n---\n# Welcome\n",
        ]);

        config()->set('app.locale', 'en');
        config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'Français']);
        app()->setLocale('en');

        // The page renders in French, but the worker's global locale is reset
        // afterwards so the next request starts from a clean slate.
        $this->get('/docs/fr')->assertOk()->assertSee('Rechercher dans la doc...');

        expect(app()->getLocale())->toBe('en');
    } finally {
        File::deleteDirectory(lang_path('vendor/laradocs/fr'));
    }
});

<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Laradocs\Contracts\DocumentParser;
use Laradocs\Support\Version;
use Laradocs\Support\VersionInfo;
use Laradocs\Support\VersionRegistry;

/**
 * Consolidated feature coverage for the whole semantic-versioning feature set
 * (US-001 .. US-012). Each `describe()` block maps to one of the eight areas
 * called out in US-013 so a regression in any of them fails CI here.
 *
 * Helpers are namespaced with a `vb` prefix to avoid colliding with the global
 * test helpers (`versionedDocs`, `registry`) defined in the sibling
 * VersioningTest / VersionRegistryTest files — Pest loads every test file into
 * the same process, so a duplicate function name would fatal.
 */

/** Resolve the container singleton so the registry wiring is exercised. */
function vbRegistry(): VersionRegistry
{
    return app(VersionRegistry::class);
}

/**
 * Turn on auto-strategy versioning (filesystem discovery) and lay down a docs
 * tree from a handle => marker map. Each handle becomes `<handle>/index.md`.
 *
 * @param  array<string, string>  $handles
 */
function vbAutoDocs(array $handles): string
{
    config()->set('laradocs.versions.enabled', true);
    config()->set('laradocs.versions.strategy', 'auto');
    config()->set('laradocs.versions.available', null);
    config()->set('laradocs.versions.aliases', []);

    $files = [];

    foreach ($handles as $handle => $marker) {
        $files["{$handle}/index.md"] = "---\ntitle: Home\norder: 1\n---\n# {$handle}\n\n{$marker}\n";
        $files["{$handle}/guide.md"] = "---\ntitle: Guide\n---\n# {$handle} guide\n\n{$marker} guide body.\n";
    }

    return test()->makeDocs($files);
}

/** Parse Markdown through the live document pipeline (extensions applied). */
function vbRender(string $markdown): string
{
    app()->forgetInstance(DocumentParser::class);

    return app(DocumentParser::class)->parse($markdown);
}

/*
|--------------------------------------------------------------------------
| 1. VersionRegistry semver detection
|--------------------------------------------------------------------------
*/

describe('VersionRegistry semver detection', function () {
    it('sorts v2.0.0 above v1.0.0 (descending)', function () {
        vbAutoDocs(['v1.0.0' => 'one', 'v2.0.0' => 'two']);

        expect(array_keys(vbRegistry()->all()))->toBe(['v2.0.0', 'v1.0.0']);
        expect(VersionRegistry::compare('v2.0.0', 'v1.0.0'))->toBe(1);
    });

    it('sorts a pre-release v2.0.0-beta below its release v2.0.0', function () {
        vbAutoDocs(['v2.0.0' => 'rel', 'v2.0.0-beta' => 'beta', 'v1.0.0' => 'old']);

        expect(array_keys(vbRegistry()->all()))->toBe(['v2.0.0', 'v2.0.0-beta', 'v1.0.0']);
        expect(VersionRegistry::compare('v2.0.0-beta', 'v2.0.0'))->toBe(-1);
    });

    it('strips the v prefix when normalising to a semver string', function () {
        vbAutoDocs(['v2.0.0' => 'two']);

        expect(vbRegistry()->get('v2.0.0')->semver)->toBe('2.0.0');
    });

    it('expands a partial handle 2.1 into 2.1.0', function () {
        vbAutoDocs(['2.1' => 'two-one']);

        expect(vbRegistry()->get('2.1')->semver)->toBe('2.1.0');
    });

    it('ignores non-semver directories during discovery', function () {
        $root = vbAutoDocs(['v1.0' => 'one']);
        (new Filesystem)->ensureDirectoryExists($root . '/_shared');
        file_put_contents($root . '/_shared/changelog.md', "# shared\n");

        expect(vbRegistry()->all())->toHaveKey('v1.0')->not->toHaveKey('_shared');
    });

    it('discovers versions from the filesystem under the auto strategy', function () {
        vbAutoDocs(['v1.0' => 'one', 'v2.0' => 'two']);

        expect(array_keys(vbRegistry()->all()))->toBe(['v2.0', 'v1.0']);
    });

    it('uses only config entries under the config strategy', function () {
        vbAutoDocs(['v9.9' => 'disk-only']);
        config()->set('laradocs.versions.strategy', 'config');
        config()->set('laradocs.versions.available', [
            'v2.0' => ['label' => 'Version 2'],
            'v1.0' => 'Version 1',
        ]);

        expect(array_keys(vbRegistry()->all()))->toBe(['v2.0', 'v1.0'])
            ->and(vbRegistry()->get('v9.9'))->toBeNull();
    });

    it('merges disk and config under the both strategy, config winning', function () {
        vbAutoDocs(['v1.0' => 'one', 'v2.0' => 'two']);
        config()->set('laradocs.versions.strategy', 'both');
        config()->set('laradocs.versions.available', [
            'v2.0' => ['label' => 'Version 2 (custom)'],
        ]);

        expect(vbRegistry()->all())->toHaveKey('v1.0')
            ->and(vbRegistry()->get('v2.0')->label)->toBe('Version 2 (custom)');
    });
});

/*
|--------------------------------------------------------------------------
| 2. Alias resolution
|--------------------------------------------------------------------------
*/

describe('alias resolution', function () {
    it('resolves the built-in latest alias to the highest stable release', function () {
        vbAutoDocs(['v1.0' => 'one', 'v2.0' => 'two', 'v3.0-beta' => 'three-beta']);

        expect(vbRegistry()->resolveAlias('latest'))->toBe('v2.0');
    });

    it('resolves the built-in stable alias and falls back to latest', function () {
        $root = vbAutoDocs(['v1.0' => 'one', 'v2.0' => 'two']);
        file_put_contents($root . '/v2.0/_version.json', '{"stable": false}');

        // v2.0 is the latest but not stable, so stable resolves to v1.0.
        expect(vbRegistry()->resolveAlias('stable'))->toBe('v1.0');
    });

    it('falls stable back to latest when nothing is flagged stable', function () {
        $root = vbAutoDocs(['v1.0' => 'one', 'v2.0' => 'two']);
        file_put_contents($root . '/v1.0/_version.json', '{"stable": false}');
        file_put_contents($root . '/v2.0/_version.json', '{"stable": false}');

        expect(vbRegistry()->stable())->toBe(vbRegistry()->latest())
            ->and(vbRegistry()->stable())->toBe('v2.0');
    });

    it('resolves a custom configured alias and overrides the built-in one', function () {
        vbAutoDocs(['v1.0' => 'one', 'v2.0' => 'two']);
        config()->set('laradocs.versions.aliases', ['old' => 'v1.0', 'latest' => 'v1.0']);

        expect(vbRegistry()->resolveAlias('old'))->toBe('v1.0')
            ->and(vbRegistry()->resolveAlias('latest'))->toBe('v1.0')
            ->and(vbRegistry()->resolveAlias('unknown'))->toBeNull();
    });
});

/*
|--------------------------------------------------------------------------
| 3. VersionInfo from _version.json
|--------------------------------------------------------------------------
*/

describe('VersionInfo from _version.json', function () {
    it('populates every flag from the sidecar', function () {
        $root = vbAutoDocs(['v1.0' => 'one']);
        file_put_contents($root . '/v1.0/_version.json', json_encode([
            'label' => 'Version 1 (legacy)',
            'semver' => '1.0.0',
            'stable' => false,
            'deprecated' => true,
            'hidden' => true,
            'deprecated_message' => 'Please upgrade to v2.',
        ]));

        $info = vbRegistry()->get('v1.0');

        expect($info)->toBeInstanceOf(VersionInfo::class)
            ->and($info->label)->toBe('Version 1 (legacy)')
            ->and($info->semver)->toBe('1.0.0')
            ->and($info->stable)->toBeFalse()
            ->and($info->deprecated)->toBeTrue()
            ->and($info->hidden)->toBeTrue()
            ->and($info->deprecatedMessage)->toBe('Please upgrade to v2.');
    });

    it('defaults a sidecar-less version to a stable, non-hidden entry', function () {
        vbAutoDocs(['v1.0' => 'one']);

        $info = vbRegistry()->get('v1.0');

        expect($info->label)->toBe('v1.0')
            ->and($info->stable)->toBeTrue()
            ->and($info->deprecated)->toBeFalse()
            ->and($info->hidden)->toBeFalse()
            ->and($info->preRelease)->toBeFalse();
    });
});

/*
|--------------------------------------------------------------------------
| 4. SetDocsVersion middleware (HTTP)
|--------------------------------------------------------------------------
*/

describe('SetDocsVersion middleware', function () {
    it('301-redirects the latest alias to its resolved version', function () {
        vbAutoDocs(['v1.0' => 'one', 'v2.0' => 'two']);

        $this->get('/docs/latest/guide')
            ->assertStatus(301)
            ->assertRedirect(url('/docs/v2.0/guide'));
    });

    it('301-redirects an unversioned url to the default version', function () {
        vbAutoDocs(['v1.0' => 'one', 'v2.0' => 'two']);
        config()->set('laradocs.versions.unversioned', 'redirect');

        $this->get('/docs/guide')
            ->assertStatus(301)
            ->assertRedirect(url('/docs/v2.0/guide'));
    });

    it('renders the default version in place for unversioned urls when configured', function () {
        vbAutoDocs(['v1.0' => 'one', 'v2.0' => 'two']);
        config()->set('laradocs.versions.unversioned', 'render');

        $this->get('/docs/guide')->assertOk()->assertSee('two guide body.');
    });

    it('404s when accessing a hidden version directly', function () {
        $root = vbAutoDocs(['v1.0' => 'one', 'v2.0' => 'two']);
        file_put_contents($root . '/v1.0/_version.json', '{"hidden": true}');

        $this->get('/docs/v1.0/guide')->assertNotFound();
        $this->get('/docs/v1.0')->assertNotFound();
    });

    it('activates a known version in place from its sub-directory', function () {
        vbAutoDocs(['v1.0' => 'one', 'v2.0' => 'two']);

        $this->get('/docs/v1.0/guide')->assertOk()->assertSee('one guide body.');
    });
});

/*
|--------------------------------------------------------------------------
| 5. ApiVersionsController (HTTP JSON)
|--------------------------------------------------------------------------
*/

describe('ApiVersionsController', function () {
    beforeEach(function () {
        config()->set('laradocs.versions.enabled', true);
        config()->set('laradocs.versions.strategy', 'config');
        config()->set('laradocs.versions.default', 'latest');
        config()->set('laradocs.versions.available', [
            'v2.0' => ['label' => 'Version 2'],
            'v1.0' => ['label' => 'Version 1', 'deprecated' => true],
            'v3.0-beta' => ['label' => 'Beta', 'stable' => false],
            'secret' => ['label' => 'Secret', 'hidden' => true],
        ]);
        $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);
    });

    it('returns the documented JSON shape', function () {
        $this->getJson('/docs/_laradocs/api/versions')
            ->assertOk()
            ->assertJsonStructure([
                'default',
                'versions' => [
                    ['key', 'label', 'semver', 'stable', 'deprecated', 'preRelease', 'latest', 'default'],
                ],
            ]);
    });

    it('flags exactly one latest version matching the registry', function () {
        $response = $this->getJson('/docs/_laradocs/api/versions')->assertOk();

        $latest = array_values(array_filter(
            $response->json('versions'),
            fn (array $v): bool => $v['latest'] === true,
        ));

        expect($latest)->toHaveCount(1)
            ->and($latest[0]['key'])->toBe(app(VersionRegistry::class)->latest());
    });

    it('reflects the deprecated flag and excludes hidden versions', function () {
        $response = $this->getJson('/docs/_laradocs/api/versions')->assertOk();

        $byKey = collect($response->json('versions'))->keyBy('key');

        expect($byKey)->toHaveKey('v1.0')
            ->and($byKey['v1.0']['deprecated'])->toBeTrue()
            ->and($byKey)->not->toHaveKey('secret');
    });

    it('marks the default version at the top level and per entry', function () {
        $response = $this->getJson('/docs/_laradocs/api/versions')->assertOk();

        $marked = array_values(array_filter(
            $response->json('versions'),
            fn (array $v): bool => $v['default'] === true,
        ));

        expect($response->json('default'))->toBe('v2.0')
            ->and($marked)->toHaveCount(1)
            ->and($marked[0]['key'])->toBe('v2.0');
    });
});

/*
|--------------------------------------------------------------------------
| 6. VersionBlockExtension (client + server modes)
|--------------------------------------------------------------------------
*/

describe('VersionBlockExtension', function () {
    beforeEach(function () {
        config()->set('laradocs.versions.inline.enabled', true);
    });

    it('emits hidden client-mode blocks with data attributes for since/until/only', function () {
        config()->set('laradocs.versions.inline.behaviour', 'client');

        $since = vbRender(":::version-since[2.0]\nNew in 2.0.\n:::");
        $until = vbRender(":::version-until[2.0]\nGone in 2.0.\n:::");
        $only = vbRender(":::version-only[1.0, 1.1]\nOnly here.\n:::");

        expect($since)->toContain('class="version-block"')
            ->and($since)->toContain('data-version-since="2.0"')
            ->and($since)->toContain('hidden')
            ->and($since)->toContain('New in 2.0.');

        expect($until)->toContain('data-version-until="2.0"')->toContain('hidden');
        expect($only)->toContain('data-version-only="1.0, 1.1"')->toContain('hidden');
    });

    it('keeps a matching server-mode block without the hidden attribute', function () {
        config()->set('laradocs.versions.inline.behaviour', 'server');
        config()->set('laradocs._current_version', 'v2.0');

        $since = vbRender(":::version-since[2.0]\nNew in 2.0.\n:::");
        $only = vbRender(":::version-only[2.0]\nThis version only.\n:::");

        expect($since)->toContain('data-version-since="2.0"')
            ->and($since)->not->toContain('hidden')
            ->and($since)->toContain('New in 2.0.');

        expect($only)->toContain('data-version-only="2.0"')->not->toContain('hidden');
    });

    it('strips a non-matching server-mode block entirely', function () {
        config()->set('laradocs.versions.inline.behaviour', 'server');
        config()->set('laradocs._current_version', 'v2.0');

        $until = vbRender("Before\n\n:::version-until[2.0]\nGone.\n:::\n\nAfter");

        expect($until)->not->toContain('version-block')
            ->and($until)->not->toContain('Gone.')
            ->and($until)->toContain('Before')
            ->and($until)->toContain('After');
    });
});

/*
|--------------------------------------------------------------------------
| 7. Outdated banner (HTTP)
|--------------------------------------------------------------------------
*/

describe('outdated banner', function () {
    it('renders on a non-default version with a link to the default and a dismiss control', function () {
        vbAutoDocs(['v1.0' => 'one', 'v2.0' => 'two']);

        $this->get('/docs/v1.0/guide')
            ->assertOk()
            ->assertSee('laradocs-version-outdated', false)
            ->assertSee(url('/docs/v2.0/guide'), false)
            ->assertSee('data-laradocs-dismiss-version-banner', false);
    });

    it('is absent on the default version', function () {
        vbAutoDocs(['v1.0' => 'one', 'v2.0' => 'two']);

        $this->get('/docs/v2.0/guide')
            ->assertOk()
            ->assertDontSee('laradocs-version-outdated', false);
    });

    it('is suppressed when versions.outdated_banner is false', function () {
        vbAutoDocs(['v1.0' => 'one', 'v2.0' => 'two']);
        config()->set('laradocs.versions.outdated_banner', false);

        $this->get('/docs/v1.0/guide')
            ->assertOk()
            ->assertDontSee('laradocs-version-outdated', false);
    });

    it('is suppressed by per-page version_banner: false metadata', function () {
        config()->set('laradocs.versions.enabled', true);
        config()->set('laradocs.versions.strategy', 'auto');
        config()->set('laradocs.versions.available', null);
        $this->makeDocs([
            'v1.0/index.md' => "---\ntitle: Home\norder: 1\n---\n# v1\n",
            'v1.0/quiet.md' => "---\ntitle: Quiet\nversion_banner: false\n---\n# Quiet\n\nNo banner.\n",
            'v2.0/index.md' => "---\ntitle: Home\norder: 1\n---\n# v2\n",
        ]);

        $this->get('/docs/v1.0/quiet')
            ->assertOk()
            ->assertDontSee('laradocs-version-outdated', false);
    });
});

/*
|--------------------------------------------------------------------------
| 8. IndexCommand --docs-version
|--------------------------------------------------------------------------
*/

describe('laradocs:index --docs-version', function () {
    it('rebuilds only the requested version', function () {
        config()->set('laradocs.versions.enabled', true);
        config()->set('laradocs.versions.available', null);
        $this->makeDocs([
            'v1/a.md' => "---\ntitle: A\n---\nbody\n",
            'v2/a.md' => "---\ntitle: A\n---\nbody\n",
            'v2/b.md' => "---\ntitle: B\n---\nbody\n",
        ]);

        $this->artisan('laradocs:index', ['--docs-version' => 'v1'])
            ->expectsOutputToContain('Indexed 1 page(s) for search (json engine).')
            ->assertSuccessful();
    });

    it('aborts on an unknown version handle', function () {
        config()->set('laradocs.versions.enabled', true);
        config()->set('laradocs.versions.available', null);
        $this->makeDocs(['v1/a.md' => "---\ntitle: A\n---\nbody\n"]);

        $this->artisan('laradocs:index', ['--docs-version' => 'v9'])
            ->expectsOutputToContain('Unknown version "v9".')
            ->assertFailed();
    });

    it('rebuilds every detected version in sequence when no version is given', function () {
        config()->set('laradocs.versions.enabled', true);
        config()->set('laradocs.versions.available', null);
        $this->makeDocs([
            'v1/a.md' => "---\ntitle: A\n---\nbody\n",
            'v2/a.md' => "---\ntitle: A\n---\nbody\n",
            'v2/b.md' => "---\ntitle: B\n---\nbody\n",
        ]);

        $this->artisan('laradocs:index')
            ->expectsOutputToContain('Rebuilding the search index for version v1.')
            ->expectsOutputToContain('Rebuilding the search index for version v2.')
            ->assertSuccessful();
    });
});

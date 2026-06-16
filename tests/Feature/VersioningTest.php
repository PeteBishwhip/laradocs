<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Laradocs\Routing\DocumentUrl;
use Laradocs\Support\CacheKey;
use Laradocs\Support\Version;

/**
 * Enable versioning and return a two-version (v1, v2) docs file map for
 * handing to $this->makeDocs().
 *
 * @return array<string, string>
 */
function versionedDocs(): array
{
    config()->set('laradocs.versions.enabled', true);
    config()->set('laradocs.versions.available', null);

    return [
        'v1/index.md' => "---\ntitle: Home\norder: 1\n---\n# V1 Home\n\nWelcome to v1.\n",
        'v1/getting-started.md' => "---\ntitle: Start\n---\n# V1 Start\n\nThis is the v1 guide.\n",
        'v2/index.md' => "---\ntitle: Home\norder: 1\n---\n# V2 Home\n\nWelcome to v2.\n",
        'v2/getting-started.md' => "---\ntitle: Start\n---\n# V2 Start\n\nThis is the v2 guide.\n",
    ];
}

/*
|--------------------------------------------------------------------------
| Version support class
|--------------------------------------------------------------------------
*/

it('returns no versions when versioning is disabled', function () {
    config()->set('laradocs.versions.enabled', false);
    config()->set('laradocs.versions.available', ['v1' => 'v1']);

    expect(Version::available())->toBe([]);
});

it('respects an explicit available array over auto-detection', function () {
    config()->set('laradocs.versions.enabled', true);
    config()->set('laradocs.versions.available', ['v2' => 'Version 2', 'v1' => 'Version 1']);

    expect(Version::available())->toBe(['v2' => 'Version 2', 'v1' => 'Version 1']);
});

it('auto-detects versions from sub-directories of the docs path', function () {
    $this->makeDocs(versionedDocs());

    expect(Version::available())->toHaveKey('v1')->toHaveKey('v2');
});

it('uses the directory name as the label when no _version.json is present', function () {
    $this->makeDocs(versionedDocs());

    expect(Version::available()['v1'])->toBe('v1');
});

it('reads a custom label from _version.json when present', function () {
    $root = $this->makeDocs(versionedDocs());
    file_put_contents($root . '/v2/_version.json', '{"label": "Version 2 (latest)"}');

    expect(Version::available()['v2'])->toBe('Version 2 (latest)');
});

it('ignores loose files in the docs path — only directories are versions', function () {
    $root = $this->makeDocs([
        'v1/index.md' => "---\ntitle: Home\n---\n# Home\n",
        'README.md' => "# Not a version\n",
    ]);
    config()->set('laradocs.versions.enabled', true);
    config()->set('laradocs.versions.available', null);

    expect(Version::available())->toHaveKey('v1')->not->toHaveKey('README.md');
});

it('returns an empty version list when the docs path does not exist', function () {
    config()->set('laradocs.versions.enabled', true);
    config()->set('laradocs.versions.available', null);
    config()->set('laradocs.docs.path', sys_get_temp_dir() . '/laradocs-missing-' . bin2hex(random_bytes(4)));

    expect(Version::available())->toBe([]);
});

it('caches the auto-detected versions so the filesystem is scanned once', function () {
    $root = $this->makeDocs(versionedDocs());
    config()->set('laradocs.cache.enabled', true);

    $key = config('laradocs.cache.key_prefix', 'laradocs') . ':versions';
    cache()->forget($key);

    try {
        expect(Version::available())->toHaveKey('v2');

        // Remove a version on disk: the cached value is still served.
        (new Filesystem)->deleteDirectory($root . '/v2');

        expect(Version::available())->toHaveKey('v2')
            ->and(cache()->get($key))->toHaveKey('v2');
    } finally {
        cache()->forget($key);
    }
});

it('reads the current version from runtime config', function () {
    expect(Version::current())->toBeNull();

    config()->set('laradocs._current_version', 'v2');

    expect(Version::current())->toBe('v2');
});

it('resolves no default version when versioning is disabled', function () {
    config()->set('laradocs.versions.enabled', false);

    expect(Version::default())->toBeNull();
});

it('uses an explicitly configured default version', function () {
    config()->set('laradocs.versions.enabled', true);
    config()->set('laradocs.versions.default', 'v2');
    config()->set('laradocs.versions.available', ['v1' => 'v1', 'v2' => 'v2']);

    expect(Version::default())->toBe('v2');
});

it('falls back to the first available version when no default is configured', function () {
    config()->set('laradocs.versions.enabled', true);
    config()->set('laradocs.versions.default', null);
    config()->set('laradocs.versions.available', ['v1' => 'v1', 'v2' => 'v2']);

    expect(Version::default())->toBe('v1');
});

it('resolves no default when versioning is enabled but no versions exist', function () {
    config()->set('laradocs.versions.enabled', true);
    config()->set('laradocs.versions.default', null);
    config()->set('laradocs.versions.available', []);

    expect(Version::default())->toBeNull();
});

it('builds a filesystem path for a version handle', function () {
    config()->set('laradocs.docs.path', '/srv/docs');

    expect(Version::pathFor('v2'))->toBe('/srv/docs' . DIRECTORY_SEPARATOR . 'v2');
});

/*
|--------------------------------------------------------------------------
| Cache namespacing
|--------------------------------------------------------------------------
*/

it('namespaces cache keys per active version', function () {
    expect(CacheKey::make('tree', 'abc'))->toBe('laradocs:tree:abc');

    config()->set('laradocs._current_version', 'v2');

    expect(CacheKey::make('tree', 'abc'))->toBe('laradocs:v2:tree:abc');
});

/*
|--------------------------------------------------------------------------
| Version-aware URL generation
|--------------------------------------------------------------------------
*/

it('prefixes generated urls with the active version', function () {
    config()->set('laradocs._current_version', 'v2');

    expect(DocumentUrl::toSlug('getting-started'))->toBe(url('/docs/v2/getting-started'))
        ->and(DocumentUrl::toSlug(''))->toBe(url('/docs/v2'))
        ->and(DocumentUrl::index())->toBe(url('/docs/v2'));
});

it('links to a specific version with forVersion', function () {
    expect(DocumentUrl::forVersion('getting-started', 'v1'))->toBe(url('/docs/v1/getting-started'))
        ->and(DocumentUrl::forVersion('', 'v1'))->toBe(url('/docs/v1'));
});

it('leaves urls unprefixed when versioning is inactive', function () {
    expect(DocumentUrl::toSlug('getting-started'))->toBe(url('/docs/getting-started'))
        ->and(DocumentUrl::index())->toBe(url('/docs'));
});

/*
|--------------------------------------------------------------------------
| Middleware + controller integration
|--------------------------------------------------------------------------
*/

it('serves the version named in the url from its sub-directory', function () {
    $this->makeDocs(versionedDocs());

    $this->get('/docs/v1/getting-started')->assertOk()->assertSee('This is the v1 guide.');
    $this->get('/docs/v2/getting-started')->assertOk()->assertSee('This is the v2 guide.');
});

it('serves a bare version root as that version landing page', function () {
    $this->makeDocs(versionedDocs());

    $this->get('/docs/v2')->assertOk()->assertSee('Welcome to v2.');
});

it('falls back to the default version when the url omits one', function () {
    $this->makeDocs(versionedDocs());
    config()->set('laradocs.versions.default', 'v2');

    // Index route: no version segment present.
    $this->get('/docs')->assertOk()->assertSee('Welcome to v2.');

    // Show route with an unprefixed slug resolves against the default version.
    $this->get('/docs/getting-started')->assertOk()->assertSee('This is the v2 guide.');
});

it('renders the version selector with each available version', function () {
    $this->makeDocs(versionedDocs());

    // The switcher cross-links the same page in the other version.
    $this->get('/docs/v1/getting-started')
        ->assertOk()
        ->assertSee('data-laradocs-version', false)
        ->assertSee('laradocs-version-current', false)
        ->assertSee(url('/docs/v2/getting-started'), false);
});

it('hides the version selector when versioning is disabled', function () {
    config()->set('laradocs.versions.enabled', false);
    $this->makeDocs([
        'getting-started.md' => "---\ntitle: Start\n---\n# Start\n\nSingle version.\n",
    ]);

    $this->get('/docs/getting-started')
        ->assertOk()
        ->assertDontSee('data-laradocs-version', false);
});

it('bypasses version resolution when no versions are available', function () {
    config()->set('laradocs.versions.enabled', true);
    config()->set('laradocs.versions.available', []);
    $this->makeDocs([
        'getting-started.md' => "---\ntitle: Start\n---\n# Start\n\nUnversioned content.\n",
    ]);

    $this->get('/docs/getting-started')->assertOk()->assertSee('Unversioned content.');
});

it('restores the docs path after a versioned request (octane-safe)', function () {
    $root = $this->makeDocs(versionedDocs());
    config()->set('laradocs.versions.default', 'v1');

    $this->get('/docs/v2/getting-started')->assertOk();

    // The worker's global config is reset so the next request starts clean.
    expect(config('laradocs.docs.path'))->toBe($root)
        ->and(config('laradocs._current_version'))->toBeNull();
});

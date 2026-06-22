<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Laradocs\Support\VersionInfo;
use Laradocs\Support\VersionRegistry;

/**
 * Resolve the registry from the container so it exercises the singleton wiring.
 */
function registry(): VersionRegistry
{
    return app(VersionRegistry::class);
}

beforeEach(function () {
    config()->set('laradocs.versions.enabled', true);
    config()->set('laradocs.versions.strategy', 'auto');
    config()->set('laradocs.versions.available', null);
    config()->set('laradocs.versions.aliases', []);
});

/*
|--------------------------------------------------------------------------
| Service registration
|--------------------------------------------------------------------------
*/

it('is registered as a singleton', function () {
    expect(app(VersionRegistry::class))->toBe(app(VersionRegistry::class));
});

/*
|--------------------------------------------------------------------------
| Auto strategy: discovery, semver filtering and sorting
|--------------------------------------------------------------------------
*/

it('auto-detects semver sub-directories and skips non-semver dirs', function () {
    $this->makeDocs([
        'v1.0/index.md' => "# v1\n",
        'v2.0/index.md' => "# v2\n",
        '_shared/changelog.md' => "# shared\n",
    ]);

    $all = registry()->all();

    expect($all)->toHaveKey('v1.0')
        ->toHaveKey('v2.0')
        ->not->toHaveKey('_shared');
});

it('sorts versions semver-descending', function () {
    $this->makeDocs([
        'v1.0.0/index.md' => "# a\n",
        'v2.0.0/index.md' => "# b\n",
        'v1.5.0/index.md' => "# c\n",
    ]);

    expect(array_keys(registry()->all()))->toBe(['v2.0.0', 'v1.5.0', 'v1.0.0']);
});

it('sorts a pre-release below its corresponding release', function () {
    $this->makeDocs([
        'v2.0.0/index.md' => "# release\n",
        'v2.0.0-beta.1/index.md' => "# beta\n",
        'v1.0.0/index.md' => "# old\n",
    ]);

    expect(array_keys(registry()->all()))->toBe(['v2.0.0', 'v2.0.0-beta.1', 'v1.0.0']);
});

it('normalises partial and prefixed handles into a semver string', function () {
    $this->makeDocs([
        'v2.1/index.md' => "# a\n",
        '3/index.md' => "# b\n",
    ]);

    $all = registry()->all();

    expect($all['v2.1']->semver)->toBe('2.1.0')
        ->and($all['3']->semver)->toBe('3.0.0');
});

it('flags the pre-release nature of a handle', function () {
    $this->makeDocs([
        'v3.0.0-beta.1/index.md' => "# beta\n",
    ]);

    $info = registry()->get('v3.0.0-beta.1');

    expect($info)->not->toBeNull()
        ->and($info->semver)->toBe('3.0.0-beta.1')
        ->and($info->preRelease)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| _version.json sidecar mapping
|--------------------------------------------------------------------------
*/

it('maps _version.json fields onto VersionInfo', function () {
    $root = $this->makeDocs([
        'v1.0/index.md' => "# v1\n",
    ]);
    file_put_contents($root . '/v1.0/_version.json', json_encode([
        'label' => 'Version 1 (legacy)',
        'semver' => '1.0.0',
        'stable' => false,
        'deprecated' => true,
        'hidden' => true,
        'deprecated_message' => 'Please upgrade to v2.',
    ]));

    $info = registry()->get('v1.0');

    expect($info->label)->toBe('Version 1 (legacy)')
        ->and($info->semver)->toBe('1.0.0')
        ->and($info->stable)->toBeFalse()
        ->and($info->deprecated)->toBeTrue()
        ->and($info->hidden)->toBeTrue()
        ->and($info->deprecatedMessage)->toBe('Please upgrade to v2.');
});

it('defaults a version to stable with the handle as its label', function () {
    $this->makeDocs([
        'v1.0/index.md' => "# v1\n",
    ]);

    $info = registry()->get('v1.0');

    expect($info->label)->toBe('v1.0')
        ->and($info->stable)->toBeTrue()
        ->and($info->deprecated)->toBeFalse()
        ->and($info->hidden)->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| get()
|--------------------------------------------------------------------------
*/

it('returns null from get() for an unknown handle', function () {
    $this->makeDocs([
        'v1.0/index.md' => "# v1\n",
    ]);

    expect(registry()->get('v9.9'))->toBeNull()
        ->and(registry()->get('v1.0'))->toBeInstanceOf(VersionInfo::class);
});

/*
|--------------------------------------------------------------------------
| latest() / stable()
|--------------------------------------------------------------------------
*/

it('latest() returns the highest non-pre-release handle', function () {
    $this->makeDocs([
        'v1.0.0/index.md' => "# a\n",
        'v2.0.0/index.md' => "# b\n",
        'v3.0.0-beta.1/index.md' => "# c\n",
    ]);

    expect(registry()->latest())->toBe('v2.0.0')
        ->and(registry()->get('v2.0.0')->latest)->toBeTrue();
});

it('latest() falls back to the highest pre-release when none are stable releases', function () {
    $this->makeDocs([
        'v3.0.0-beta.1/index.md' => "# a\n",
        'v3.0.0-alpha.1/index.md' => "# b\n",
    ]);

    expect(registry()->latest())->toBe('v3.0.0-beta.1');
});

it('latest() is null when there are no versions', function () {
    $this->makeDocs([
        'README.md' => "# nope\n",
    ]);

    expect(registry()->latest())->toBeNull();
});

it('stable() returns the highest handle flagged stable', function () {
    $root = $this->makeDocs([
        'v1.0/index.md' => "# v1\n",
        'v2.0/index.md' => "# v2\n",
    ]);
    file_put_contents($root . '/v2.0/_version.json', '{"stable": false}');

    expect(registry()->stable())->toBe('v1.0');
});

it('stable() falls back to latest() when nothing is flagged stable', function () {
    $root = $this->makeDocs([
        'v1.0/index.md' => "# v1\n",
        'v2.0/index.md' => "# v2\n",
    ]);
    file_put_contents($root . '/v1.0/_version.json', '{"stable": false}');
    file_put_contents($root . '/v2.0/_version.json', '{"stable": false}');

    expect(registry()->stable())->toBe(registry()->latest())
        ->and(registry()->stable())->toBe('v2.0');
});

/*
|--------------------------------------------------------------------------
| Alias resolution
|--------------------------------------------------------------------------
*/

it('resolves the built-in latest and stable aliases', function () {
    $root = $this->makeDocs([
        'v1.0/index.md' => "# v1\n",
        'v2.0/index.md' => "# v2\n",
    ]);
    file_put_contents($root . '/v2.0/_version.json', '{"stable": false}');

    expect(registry()->resolveAlias('latest'))->toBe('v2.0')
        ->and(registry()->resolveAlias('stable'))->toBe('v1.0');
});

it('resolves a configured alias from versions.aliases', function () {
    $this->makeDocs([
        'v1.0/index.md' => "# v1\n",
        'v2.0/index.md' => "# v2\n",
    ]);
    config()->set('laradocs.versions.aliases', ['current' => 'v1.0']);

    expect(registry()->resolveAlias('current'))->toBe('v1.0')
        ->and(registry()->resolveAlias('unknown'))->toBeNull();
});

it('lets a configured alias override the built-in computed alias', function () {
    $this->makeDocs([
        'v1.0/index.md' => "# v1\n",
        'v2.0/index.md' => "# v2\n",
    ]);
    config()->set('laradocs.versions.aliases', ['latest' => 'v1.0']);

    expect(registry()->resolveAlias('latest'))->toBe('v1.0');
});

it('recognises alias handles', function () {
    config()->set('laradocs.versions.aliases', ['current' => 'v1.0']);

    expect(registry()->isAlias('latest'))->toBeTrue()
        ->and(registry()->isAlias('stable'))->toBeTrue()
        ->and(registry()->isAlias('current'))->toBeTrue()
        ->and(registry()->isAlias('v1.0'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Strategies: config and both
|--------------------------------------------------------------------------
*/

it('uses only config entries under the config strategy', function () {
    $this->makeDocs([
        'v1.0/index.md' => "# v1\n",
        'v9.9/index.md' => "# disk-only\n",
    ]);
    config()->set('laradocs.versions.strategy', 'config');
    config()->set('laradocs.versions.available', [
        'v2.0' => ['label' => 'Version 2', 'stable' => true],
        'v1.0' => 'Version 1',
    ]);

    $all = registry()->all();

    expect(array_keys($all))->toBe(['v2.0', 'v1.0'])
        ->and($all['v2.0']->label)->toBe('Version 2')
        ->and($all['v1.0']->label)->toBe('Version 1');
});

it('merges disk and config under the both strategy with config winning', function () {
    $this->makeDocs([
        'v1.0/index.md' => "# v1\n",
        'v2.0/index.md' => "# v2\n",
    ]);
    config()->set('laradocs.versions.strategy', 'both');
    config()->set('laradocs.versions.available', [
        'v2.0' => ['label' => 'Version 2 (custom)'],
    ]);

    $all = registry()->all();

    expect($all)->toHaveKey('v1.0')
        ->and($all['v2.0']->label)->toBe('Version 2 (custom)');
});

/*
|--------------------------------------------------------------------------
| Caching
|--------------------------------------------------------------------------
*/

it('caches the resolved version list under the versions key', function () {
    config()->set('laradocs.cache.enabled', true);
    $root = $this->makeDocs([
        'v1.0/index.md' => "# v1\n",
        'v2.0/index.md' => "# v2\n",
    ]);

    $key = config('laradocs.cache.key_prefix', 'laradocs') . ':versions';
    cache()->forget($key);

    try {
        expect(registry()->all())->toHaveKey('v2.0');

        // Remove a version on disk: the cached value is still served.
        (new Filesystem)->deleteDirectory($root . '/v2.0');

        expect(registry()->all())->toHaveKey('v2.0')
            ->and(cache()->get($key))->toHaveKey('v2.0');
    } finally {
        cache()->forget($key);
    }
});

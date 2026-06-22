<?php

declare(strict_types=1);

use Laradocs\Support\VersionRegistry;

/**
 * Enable versioning with a fixed, config-driven version list so the API
 * response is deterministic without touching the filesystem.
 */
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

it('returns a versions array and a default field', function () {
    $this->getJson('/docs/_laradocs/api/versions')
        ->assertOk()
        ->assertJsonStructure(['versions', 'default']);
});

it('each version entry carries the full metadata shape', function () {
    $this->getJson('/docs/_laradocs/api/versions')
        ->assertOk()
        ->assertJsonStructure([
            'versions' => [
                ['key', 'label', 'semver', 'stable', 'deprecated', 'preRelease', 'latest', 'default'],
            ],
        ]);
});

it('excludes hidden versions from the response', function () {
    $response = $this->getJson('/docs/_laradocs/api/versions')->assertOk();

    $keys = array_column($response->json('versions'), 'key');

    expect($keys)->toContain('v2.0')
        ->and($keys)->toContain('v1.0')
        ->and($keys)->not->toContain('secret');
});

it('flags the latest version to match VersionRegistry::latest()', function () {
    $response = $this->getJson('/docs/_laradocs/api/versions')->assertOk();

    $latest = array_values(array_filter(
        $response->json('versions'),
        fn (array $v): bool => $v['latest'] === true,
    ));

    expect($latest)->toHaveCount(1)
        ->and($latest[0]['key'])->toBe(app(VersionRegistry::class)->latest());
});

it('marks the default version with default true at top level and per entry', function () {
    $response = $this->getJson('/docs/_laradocs/api/versions')->assertOk();

    $default = $response->json('default');

    $marked = array_values(array_filter(
        $response->json('versions'),
        fn (array $v): bool => $v['default'] === true,
    ));

    expect($default)->toBe('v2.0')
        ->and($marked)->toHaveCount(1)
        ->and($marked[0]['key'])->toBe('v2.0');
});

it('reflects deprecated and stable metadata', function () {
    $response = $this->getJson('/docs/_laradocs/api/versions')->assertOk();

    $byKey = collect($response->json('versions'))->keyBy('key');

    expect($byKey['v1.0']['deprecated'])->toBeTrue()
        ->and($byKey['v3.0-beta']['stable'])->toBeFalse()
        ->and($byKey['v3.0-beta']['preRelease'])->toBeTrue();
});

it('404s when docs are disabled', function () {
    config()->set('laradocs.enabled', false);

    $this->getJson('/docs/_laradocs/api/versions')->assertNotFound();
});

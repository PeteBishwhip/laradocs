<?php

declare(strict_types=1);

use Laradocs\Support\VersionInfo;

it('carries version metadata with safe defaults', function () {
    $info = new VersionInfo(key: 'v2.0', label: 'v2.0');

    expect($info->key)->toBe('v2.0')
        ->and($info->label)->toBe('v2.0')
        ->and($info->semver)->toBeNull()
        ->and($info->stable)->toBeTrue()
        ->and($info->deprecated)->toBeFalse()
        ->and($info->hidden)->toBeFalse()
        ->and($info->latest)->toBeFalse()
        ->and($info->preRelease)->toBeFalse()
        ->and($info->deprecatedMessage)->toBeNull();
});

it('carries fully populated version metadata', function () {
    $info = new VersionInfo(
        key: 'v3.0-beta',
        label: 'v3.0 Beta',
        semver: '3.0.0',
        stable: false,
        deprecated: true,
        hidden: true,
        latest: true,
        preRelease: true,
        deprecatedMessage: 'Use v2.0 instead.',
    );

    expect($info->semver)->toBe('3.0.0')
        ->and($info->stable)->toBeFalse()
        ->and($info->deprecated)->toBeTrue()
        ->and($info->hidden)->toBeTrue()
        ->and($info->latest)->toBeTrue()
        ->and($info->preRelease)->toBeTrue()
        ->and($info->deprecatedMessage)->toBe('Use v2.0 instead.');
});

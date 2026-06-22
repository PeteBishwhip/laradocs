<?php

declare(strict_types=1);

namespace Laradocs\Support;

/**
 * A typed value object carrying the rich metadata for a single documentation
 * version through the system.
 *
 * Most fields originate from the `laradocs.versions.available` config (or a
 * version directory's `_version.json` sidecar). A handful are computed by the
 * version registry as it assembles the full list: {@see self::$latest} (the
 * newest stable version) and {@see self::$preRelease} (derived from the
 * normalised semver).
 */
final readonly class VersionInfo
{
    public function __construct(
        public string $key,
        public string $label,
        public ?string $semver = null,
        public bool $stable = true,
        public bool $deprecated = false,
        public bool $hidden = false,
        public bool $latest = false,
        public bool $preRelease = false,
        public ?string $deprecatedMessage = null,
    ) {}
}

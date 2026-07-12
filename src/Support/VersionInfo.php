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
final class VersionInfo
{
    /**
     * @readonly
     * @var string
     */
    public $key;
    /**
     * @readonly
     * @var string
     */
    public $label;
    /**
     * @readonly
     * @var string|null
     */
    public $semver;
    /**
     * @readonly
     * @var bool
     */
    public $stable = true;
    /**
     * @readonly
     * @var bool
     */
    public $deprecated = false;
    /**
     * @readonly
     * @var bool
     */
    public $hidden = false;
    /**
     * @readonly
     * @var bool
     */
    public $latest = false;
    /**
     * @readonly
     * @var bool
     */
    public $preRelease = false;
    /**
     * @readonly
     * @var string|null
     */
    public $deprecatedMessage;
    public function __construct(string $key, string $label, ?string $semver = null, bool $stable = true, bool $deprecated = false, bool $hidden = false, bool $latest = false, bool $preRelease = false, ?string $deprecatedMessage = null)
    {
        $this->key = $key;
        $this->label = $label;
        $this->semver = $semver;
        $this->stable = $stable;
        $this->deprecated = $deprecated;
        $this->hidden = $hidden;
        $this->latest = $latest;
        $this->preRelease = $preRelease;
        $this->deprecatedMessage = $deprecatedMessage;
    }
}

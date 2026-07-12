<?php

declare(strict_types=1);

namespace Laradocs\Support;

use Laradocs\Http\Middleware\SetDocsVersion;

/**
 * Resolves which documentation versions are available and which one the current
 * request is serving. Discovery, sorting and alias resolution are delegated to
 * the {@see VersionRegistry} singleton; this class keeps a thin, backward-
 * compatible static facade over it (notably {@see self::available()} returning a
 * `[handle => label]` array) plus the request-scoped helpers.
 *
 * The active version for a given request is set by {@see SetDocsVersion}
 * into the `laradocs._current_version` runtime config key and can be read back
 * with {@see self::current()}.
 */
final class Version
{
    /**
     * The versions available for the documentation.
     *
     * When `laradocs.versions.available` is an array it is returned as-is —
     * an explicit override that wins over auto-detection. An empty array
     * disables the selector outright. When it is null (the default) the docs
     * path is scanned for sub-directories and the result is cached to avoid
     * repeated filesystem hits.
     *
     * Each version directory may contain an optional `_version.json` file with
     * a `"label"` key for a custom display name; otherwise the directory name
     * is used.
     *
     * @return array<string, string> Keys are version handles; values are labels.
     */
    public static function available(): array
    {
        if (! Config::bool('laradocs.versions.enabled', false)) {
            return [];
        }

        $explicit = config('laradocs.versions.available');

        if (is_array($explicit)) {
            /** @var array<string, string> $explicit */
            return $explicit;
        }

        return array_map(
            static function (VersionInfo $info): string {
                return $info->label;
            },
            self::registry()->all(),
        );
    }

    /**
     * The shared {@see VersionRegistry} singleton — the authoritative source of
     * truth for version discovery, sorting and alias resolution. Resolved from
     * the container so the assembled list is built once per request.
     */
    public static function registry(): VersionRegistry
    {
        return app(VersionRegistry::class);
    }

    /**
     * The handle carrying the `latest` flag, delegating to the registry.
     */
    public static function latest(): ?string
    {
        return self::registry()->latest();
    }

    /**
     * The {@see VersionInfo} for a handle, or null when it is not a version.
     */
    public static function info(string $key): ?VersionInfo
    {
        return self::registry()->get($key);
    }

    /**
     * Whether the given handle is the resolved default version.
     */
    public static function isDefault(string $v): bool
    {
        return $v === self::default();
    }

    /**
     * The version handle active for the current request.
     *
     * Set by {@see SetDocsVersion} before each
     * request and restored afterwards. Returns null when versioning is off.
     */
    public static function current(): ?string
    {
        return Config::nullableString('laradocs._current_version');
    }

    /**
     * The version to fall back to when no explicit version is present in a URL.
     *
     * Resolution order:
     *   1. `laradocs.versions.default` (or LARADOCS_VERSION_DEFAULT env var). A
     *      `latest` / `stable` keyword (or any configured alias) is resolved to a
     *      concrete handle via {@see VersionRegistry::resolveAlias()}.
     *   2. The first detected version from {@see self::available()}.
     *
     * Returns null when versioning is disabled.
     */
    public static function default(): ?string
    {
        if (! Config::bool('laradocs.versions.enabled', false)) {
            return null;
        }

        $configured = Config::nullableString('laradocs.versions.default');

        if ($configured !== null && $configured !== '') {
            return self::registry()->resolveAlias($configured) ?? $configured;
        }

        $available = self::available();
        $first = array_key_first($available);

        return is_string($first) && $first !== '' ? $first : null;
    }

    /**
     * The absolute filesystem path for a given version handle.
     *
     * Versions live as sub-directories of the base docs path:
     *   {laradocs.docs.path}/{version}/
     */
    public static function pathFor(string $version): string
    {
        return rtrim(Config::string('laradocs.docs.path'), '/\\') . DIRECTORY_SEPARATOR . $version;
    }

    /**
     * The docs path the loader should read for the current request: the base
     * path when no version is active, or the active version's sub-directory.
     *
     * The base `laradocs.docs.path` is never mutated, so version auto-detection
     * keeps scanning the parent directory for the lifetime of the request.
     */
    public static function docsPath(): string
    {
        $current = self::current();

        return $current !== null ? self::pathFor($current) : Config::string('laradocs.docs.path');
    }
}

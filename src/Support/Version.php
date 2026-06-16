<?php

declare(strict_types=1);

namespace Laradocs\Support;

use Laradocs\Http\Middleware\SetDocsVersion;

/**
 * Resolves which documentation versions are available and which one the current
 * request is serving. Auto-detection scans sub-directories of the configured
 * docs path, so developers don't need to maintain a config list alongside the
 * filesystem.
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

        return self::fromCache();
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
     *   1. `laradocs.versions.default` (or LARADOCS_VERSION_DEFAULT env var).
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
            return $configured;
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

    /**
     * Return the auto-detected version list from cache, or scan directly when
     * caching is disabled.
     *
     * @return array<string, string>
     */
    private static function fromCache(): array
    {
        if (! Config::bool('laradocs.cache.enabled', true)) {
            return self::scan();
        }

        $key = Config::string('laradocs.cache.key_prefix', 'laradocs') . ':versions';
        $ttl = Config::nullableInt('laradocs.cache.ttl') ?? 86400;

        return cache()
            ->store(Config::nullableString('laradocs.cache.store'))
            ->remember($key, $ttl, self::scan(...));
    }

    /**
     * Scan the docs path for version sub-directories.
     *
     * @return array<string, string>
     */
    private static function scan(): array
    {
        $base = Config::string('laradocs.docs.path');

        if (! is_dir($base)) {
            return [];
        }

        $versions = [];

        foreach (array_diff((array) scandir($base), ['.', '..']) as $entry) {
            $entry = (string) $entry;

            if (! is_dir("{$base}/{$entry}")) {
                continue;
            }

            $versions[$entry] = self::label($entry, "{$base}/{$entry}");
        }

        return $versions;
    }

    /**
     * The human-readable label for a version directory.
     *
     * Reads `_version.json` when present and it exposes a string `label` key;
     * otherwise the version handle itself is used as the label. A plain JSON
     * sidecar is used rather than an executable PHP file so labels can come
     * from a separate docs branch or directory without executing its code.
     */
    private static function label(string $handle, string $dir): string
    {
        $meta = "{$dir}/_version.json";

        if (is_file($meta)) {
            /** @var mixed $data */
            $data = json_decode((string) file_get_contents($meta), true);

            if (is_array($data) && isset($data['label']) && is_string($data['label'])) {
                return $data['label'];
            }
        }

        return $handle;
    }
}

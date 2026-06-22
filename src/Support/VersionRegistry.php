<?php

declare(strict_types=1);

namespace Laradocs\Support;

use Laradocs\LaradocsServiceProvider;

/**
 * The single authoritative source of truth for the documentation versions a
 * site exposes. It discovers versions (from the filesystem, the config, or
 * both), normalises and sorts them with semver semantics, and resolves the
 * `latest` / `stable` aliases (plus any configured in `versions.aliases`).
 *
 * Registered as a singleton in {@see LaradocsServiceProvider} so the
 * assembled, sorted list is built once per request and shared by every
 * consumer (middleware, the selector view, URL generation, …).
 *
 * Each version is described by a {@see VersionInfo}. Metadata originates from a
 * version directory's optional `_version.json` sidecar or an explicit
 * `versions.available` config entry; the registry layers on the derived
 * {@see VersionInfo::$semver}, {@see VersionInfo::$preRelease} and
 * {@see VersionInfo::$latest} fields.
 */
final class VersionRegistry
{
    /**
     * Matches version handles such as `v2`, `2.1`, `v3.0.0` and
     * `v3.0.0-beta.1`. The `v` prefix and the minor/patch components are
     * optional; anything else (e.g. a `_shared` directory) is rejected.
     */
    private const SEMVER_PATTERN = '/^v?(\d+)(?:\.(\d+))?(?:\.(\d+))?(?:-(.+))?$/i';

    /**
     * Every available version keyed by its handle, sorted semver-descending.
     *
     * The result is cached under the `{key_prefix}:versions` key (shared TTL
     * pattern) so the docs path is scanned at most once per cache window.
     *
     * @return array<string, VersionInfo>
     */
    public function all(): array
    {
        if (! Config::bool('laradocs.cache.enabled', true)) {
            return $this->resolve();
        }

        $key = Config::string('laradocs.cache.key_prefix', 'laradocs') . ':versions';
        $ttl = Config::nullableInt('laradocs.cache.ttl') ?? 86400;

        /** @var array<string, VersionInfo> $cached */
        $cached = cache()
            ->store(Config::nullableString('laradocs.cache.store'))
            ->remember($key, $ttl, fn (): array => $this->resolve());

        return $cached;
    }

    /**
     * The {@see VersionInfo} for a handle, or null when it is not a version.
     */
    public function get(string $key): ?VersionInfo
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * The highest non-pre-release version handle, falling back to the highest
     * pre-release when no stable release exists. Null when there are none.
     */
    public function latest(): ?string
    {
        foreach ($this->all() as $key => $info) {
            if ($info->latest) {
                return (string) $key;
            }
        }

        return null;
    }

    /**
     * The highest handle flagged `stable`, falling back to {@see self::latest()}
     * when nothing is explicitly stable.
     */
    public function stable(): ?string
    {
        foreach ($this->all() as $key => $info) {
            if ($info->stable) {
                return (string) $key;
            }
        }

        return $this->latest();
    }

    /**
     * Resolve an alias handle to a concrete version key.
     *
     * A handle configured in `versions.aliases` wins, so a site can pin
     * `latest` / `stable` to a fixed version. Otherwise the built-in `latest`
     * and `stable` aliases are computed from the version list. Returns null for
     * an unknown handle.
     */
    public function resolveAlias(string $alias): ?string
    {
        $aliases = Config::array('laradocs.versions.aliases');

        if (isset($aliases[$alias]) && is_string($aliases[$alias]) && $aliases[$alias] !== '') {
            return $aliases[$alias];
        }

        return match ($alias) {
            'latest' => $this->latest(),
            'stable' => $this->stable(),
            default => null,
        };
    }

    /**
     * Whether a URL segment is a recognised alias handle — the built-in
     * `latest` / `stable` or any key configured in `versions.aliases`.
     */
    public function isAlias(string $segment): bool
    {
        if ($segment === 'latest' || $segment === 'stable') {
            return true;
        }

        return array_key_exists($segment, Config::array('laradocs.versions.aliases'));
    }

    /**
     * Compare two raw version handles by semver, returning -1, 0 or 1.
     *
     * Shares the registry's parsing and comparison rules so consumers (e.g. the
     * inline `:::version-*` directives) evaluate versions identically to the
     * sort order. An unparseable handle sorts below a parseable one and equal to
     * another unparseable handle.
     */
    public static function compare(string $a, string $b): int
    {
        $pa = self::parseSemver($a);
        $pb = self::parseSemver($b);

        if ($pa === null || $pb === null) {
            return ($pa === null ? 0 : 1) <=> ($pb === null ? 0 : 1);
        }

        return self::compareSemver($pa, $pb);
    }

    /**
     * Assemble the version list for the configured strategy, then sort it and
     * mark the latest entry.
     *
     * @return array<string, VersionInfo>
     */
    private function resolve(): array
    {
        $versions = match (Config::string('laradocs.versions.strategy', 'auto')) {
            'config' => $this->fromConfig(),
            'both' => $this->merge($this->scan(), $this->fromConfig()),
            default => $this->scan(),
        };

        return $this->finalise($versions);
    }

    /**
     * Scan the docs path for sub-directories whose name is a semver handle,
     * reading each one's `_version.json` sidecar for richer metadata.
     *
     * @return array<string, VersionInfo>
     */
    private function scan(): array
    {
        $base = Config::string('laradocs.docs.path');

        if (! is_dir($base)) {
            return [];
        }

        $versions = [];

        foreach (array_diff((array) scandir($base), ['.', '..']) as $entry) {
            $entry = (string) $entry;
            $dir = $base . DIRECTORY_SEPARATOR . $entry;

            if (! is_dir($dir) || self::parseSemver($entry) === null) {
                continue;
            }

            $versions[$entry] = $this->makeInfo($entry, $this->readSidecar($dir));
        }

        return $versions;
    }

    /**
     * Build the version list purely from the `versions.available` config array,
     * honouring both the legacy string-label form and the richer metadata form.
     *
     * @return array<string, VersionInfo>
     */
    private function fromConfig(): array
    {
        $versions = [];

        foreach (Config::array('laradocs.versions.available') as $key => $value) {
            $handle = (string) $key;

            $meta = is_array($value)
                ? $value
                : ['label' => is_string($value) ? $value : $handle];

            $versions[$handle] = $this->makeInfo($handle, $meta);
        }

        return $versions;
    }

    /**
     * Merge auto-detected versions with config entries; explicit config wins on
     * a key collision so hand-authored metadata overrides what's on disk.
     *
     * @param  array<string, VersionInfo>  $auto
     * @param  array<string, VersionInfo>  $config
     * @return array<string, VersionInfo>
     */
    private function merge(array $auto, array $config): array
    {
        foreach ($config as $key => $info) {
            $auto[$key] = $info;
        }

        return $auto;
    }

    /**
     * Sort the assembled list semver-descending and flag the latest entry.
     *
     * @param  array<string, VersionInfo>  $versions
     * @return array<string, VersionInfo>
     */
    private function finalise(array $versions): array
    {
        uasort($versions, fn (VersionInfo $a, VersionInfo $b): int => self::compareInfo($b, $a));

        $latest = $this->computeLatest($versions);

        if ($latest !== null) {
            $versions[$latest] = $this->withLatest($versions[$latest]);
        }

        return $versions;
    }

    /**
     * The handle that should carry the `latest` flag: the first non-pre-release
     * in the descending list, or the highest entry when all are pre-releases.
     *
     * @param  array<string, VersionInfo>  $versions
     */
    private function computeLatest(array $versions): ?string
    {
        $fallback = null;

        foreach ($versions as $key => $info) {
            $fallback ??= (string) $key;

            if (! $info->preRelease) {
                return (string) $key;
            }
        }

        return $fallback;
    }

    /**
     * Map a metadata array (a `_version.json` sidecar or a config entry) onto a
     * {@see VersionInfo}, deriving the normalised semver and pre-release flag.
     *
     * @param  array<array-key, mixed>  $meta
     */
    private function makeInfo(string $key, array $meta): VersionInfo
    {
        $raw = isset($meta['semver']) && is_string($meta['semver']) ? $meta['semver'] : $key;
        $parsed = self::parseSemver($raw);

        return new VersionInfo(
            key: $key,
            label: isset($meta['label']) && is_string($meta['label']) ? $meta['label'] : $key,
            semver: $parsed !== null ? self::normalise($parsed) : null,
            stable: isset($meta['stable']) ? (bool) $meta['stable'] : true,
            deprecated: isset($meta['deprecated']) && (bool) $meta['deprecated'],
            hidden: isset($meta['hidden']) && (bool) $meta['hidden'],
            latest: false,
            preRelease: $parsed !== null && $parsed[3] !== null,
            deprecatedMessage: isset($meta['deprecated_message']) && is_string($meta['deprecated_message'])
                ? $meta['deprecated_message']
                : null,
        );
    }

    /**
     * Return a copy of the given info with its `latest` flag set.
     */
    private function withLatest(VersionInfo $info): VersionInfo
    {
        return new VersionInfo(
            key: $info->key,
            label: $info->label,
            semver: $info->semver,
            stable: $info->stable,
            deprecated: $info->deprecated,
            hidden: $info->hidden,
            latest: true,
            preRelease: $info->preRelease,
            deprecatedMessage: $info->deprecatedMessage,
        );
    }

    /**
     * Read and decode a version directory's `_version.json` sidecar.
     *
     * @return array<array-key, mixed>
     */
    private function readSidecar(string $dir): array
    {
        $file = $dir . DIRECTORY_SEPARATOR . '_version.json';

        if (! is_file($file)) {
            return [];
        }

        /** @var mixed $data */
        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? $data : [];
    }

    /**
     * Parse a raw version handle into its semver components.
     *
     * Accepts an optional `v` prefix and partial versions (`2.1` → 2.1.0). A
     * trailing `-suffix` is captured as the pre-release identifier.
     *
     * @return array{0: int, 1: int, 2: int, 3: string|null}|null
     */
    private static function parseSemver(string $raw): ?array
    {
        if (! preg_match(self::SEMVER_PATTERN, trim($raw), $m)) {
            return null;
        }

        return [
            (int) $m[1],
            isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0,
            isset($m[3]) && $m[3] !== '' ? (int) $m[3] : 0,
            $m[4] ?? null,
        ];
    }

    /**
     * Render parsed semver components back into a normalised string, e.g.
     * `[3, 0, 0, 'beta.1']` → `3.0.0-beta.1`.
     *
     * @param  array{0: int, 1: int, 2: int, 3: string|null}  $parsed
     */
    private static function normalise(array $parsed): string
    {
        $core = "{$parsed[0]}.{$parsed[1]}.{$parsed[2]}";

        return $parsed[3] !== null ? "{$core}-{$parsed[3]}" : $core;
    }

    /**
     * Compare two {@see VersionInfo} by semver. Entries without a parseable
     * semver sort below those that have one.
     */
    private static function compareInfo(VersionInfo $a, VersionInfo $b): int
    {
        $pa = $a->semver !== null ? self::parseSemver($a->semver) : null;
        $pb = $b->semver !== null ? self::parseSemver($b->semver) : null;

        if ($pa === null || $pb === null) {
            return ($pa === null ? 0 : 1) <=> ($pb === null ? 0 : 1);
        }

        return self::compareSemver($pa, $pb);
    }

    /**
     * Compare two parsed semver tuples. Equal core versions rank a release
     * above its pre-release; two pre-releases compare lexically.
     *
     * @param  array{0: int, 1: int, 2: int, 3: string|null}  $a
     * @param  array{0: int, 1: int, 2: int, 3: string|null}  $b
     */
    private static function compareSemver(array $a, array $b): int
    {
        foreach ([0, 1, 2] as $i) {
            if ($a[$i] !== $b[$i]) {
                return $a[$i] <=> $b[$i];
            }
        }

        // Core versions match: a release (no pre-release) outranks a pre-release.
        if ($a[3] === null || $b[3] === null) {
            return ($a[3] === null ? 1 : 0) <=> ($b[3] === null ? 1 : 0);
        }

        return strcmp($a[3], $b[3]) <=> 0;
    }
}

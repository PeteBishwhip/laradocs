<?php

declare(strict_types=1);

namespace Laradocs\Support;

use Closure;
use Illuminate\Http\Request;
use Laradocs\Routing\DocumentUrl;

/**
 * Resolves which interface locales are available and which one a given request
 * should render in. Auto-detection scans the published translation directory so
 * developers don't have to keep a config array in sync with the files on disk.
 */
final class Locale
{
    /**
     * Path, relative to the application's lang directory, where the package's
     * translations are published (`lang/vendor/laradocs`).
     */
    private const PUBLISHED = 'vendor/laradocs';

    /**
     * Optional application-supplied callback that determines whether cookie
     * persistence is active for the current request. When registered it takes
     * priority over the `laradocs.locale.cookie` config value.
     *
     * Register via `Laradocs::cookiesEnabled(fn () => ...)` in a service provider.
     */
    private static ?Closure $cookieResolver = null;

    /**
     * Register a callback that determines whether cookie persistence is enabled.
     *
     * The callback is evaluated on every check (i.e. per request), so it can
     * inspect session state, consent flags, or any other runtime condition.
     * Pass `null` to clear a previously registered callback and fall back to
     * the `laradocs.locale.cookie` config value.
     */
    public static function setCookieResolver(?Closure $resolver): void
    {
        self::$cookieResolver = $resolver;
    }

    /**
     * Whether cookie persistence is currently enabled.
     *
     * When a callback has been registered via `Laradocs::cookiesEnabled()` it is
     * called and its boolean result is returned. Otherwise the config flag
     * `laradocs.locale.cookie` (default `false`) is used.
     */
    public static function cookieEnabled(): bool
    {
        if (self::$cookieResolver !== null) {
            return (bool) (self::$cookieResolver)();
        }

        return Config::bool('laradocs.locale.cookie', false);
    }

    /**
     * The locales available for the documentation interface.
     *
     * When `laradocs.locale.available` is an array it is returned as-is — an
     * explicit override that wins over auto-detection. An empty array therefore
     * disables the selector outright. When it is null (the default) the
     * published translation directory is scanned for locale sub-directories and
     * the result is cached to avoid repeated filesystem hits on every request.
     *
     * Each locale directory may contain an optional `meta.php` that returns an
     * array with a `'label'` key; when absent the locale code is used as the
     * human-readable label.
     *
     * @return array<string, string> Keys are locale codes; values are labels.
     */
    public static function available(): array
    {
        $explicit = config('laradocs.locale.available');

        if (is_array($explicit)) {
            /** @var array<string, string> $explicit */
            return $explicit;
        }

        if (! Config::bool('laradocs.cache.enabled', true)) {
            return self::scan();
        }

        $key = Config::string('laradocs.cache.key_prefix', 'laradocs') . ':locales';
        $ttl = Config::nullableInt('laradocs.cache.ttl') ?? 86400;

        return cache()
            ->store(Config::nullableString('laradocs.cache.store'))
            ->remember($key, $ttl, self::scan(...));
    }

    /**
     * Whether locales are reflected in the URL path (e.g. /docs/fr/guide) rather
     * than via a `?lang=` query parameter.
     *
     * Gated on the `laradocs.locale.url` flag (default true) and only active once
     * two or more locales are available — a single-locale site has nothing to
     * disambiguate, so its URLs stay free of a redundant language segment.
     */
    public static function urlEnabled(): bool
    {
        return Config::bool('laradocs.locale.url', true) && count(self::available()) > 1;
    }

    /**
     * The URL path segment for the active locale, or null when none should be
     * emitted — i.e. URL locales are disabled, the active locale is the default
     * (served unprefixed so canonical URLs stay clean) or it is not an available
     * locale. Used by {@see DocumentUrl} to scope links.
     */
    public static function segment(): ?string
    {
        if (! self::urlEnabled()) {
            return null;
        }

        $locale = (string) app()->getLocale();

        return $locale !== self::fallback() && array_key_exists($locale, self::available())
            ? $locale
            : null;
    }

    /**
     * Split a docs path into its leading locale segment and the remaining path.
     *
     * The first segment is treated as a locale only when it matches one of the
     * available locales; otherwise the locale is null and the path is returned
     * untouched. This keeps a real document slug that happens to look like a
     * locale ("en/...") working unless that code is actually offered.
     *
     * @return array{0: ?string, 1: string} `[locale|null, remainingPath]`
     */
    public static function split(string $path): array
    {
        $path = ltrim($path, '/');

        if (! self::urlEnabled()) {
            return [null, $path];
        }

        $segment = explode('/', $path)[0];

        if ($segment !== '' && array_key_exists($segment, self::available())) {
            return [$segment, ltrim((string) substr($path, strlen($segment)), '/')];
        }

        return [null, $path];
    }

    /**
     * The locale the docs fall back to when a visitor hasn't picked one.
     *
     * Resolution order:
     *   1. An explicit `laradocs.locale.default` (or LARADOCS_LOCALE).
     *   2. The host application's locale, when it has a translation directory.
     *   3. The first detected `available` locale.
     *   4. The application locale as a last resort.
     */
    public static function fallback(): string
    {
        $configured = Config::nullableString('laradocs.locale.default');

        if ($configured !== null && $configured !== '') {
            return $configured;
        }

        $available = self::available();
        $appLocale = (string) app()->getLocale();

        if (array_key_exists($appLocale, $available)) {
            return $appLocale;
        }

        $first = array_key_first($available);

        return is_string($first) && $first !== '' ? $first : $appLocale;
    }

    /**
     * The validated `?lang=` query parameter, or null if absent or unrecognised.
     *
     * Centralises the single point where the request is interrogated for an
     * explicit locale choice, so neither this class nor the middleware need to
     * repeat the available-locales check independently.
     */
    public static function explicitChoice(Request $request): ?string
    {
        $available = self::available();
        $lang = $request->query('lang');

        return is_string($lang) && $lang !== '' && array_key_exists($lang, $available) ? $lang : null;
    }

    /**
     * The locale to render the current docs request in.
     *
     * Resolution order (first match wins):
     *   1. The `?lang=` query parameter, validated against available locales.
     *   2. The `laradocs_locale` cookie, **only** when `locale.cookie` is
     *      enabled — the cookie is both written and read only when that flag is
     *      on, so it can be disabled for EU cookie-consent compliance.
     *   3. The browser's `Accept-Language` header, when
     *      `laradocs.locale.detect_browser` is true (the default) — the
     *      highest-quality header locale that maps to an available locale wins.
     *   4. The configured fallback locale.
     *
     * Unknown codes are ignored at every step so neither the query string nor
     * the browser header can force the UI into an untranslated locale.
     */
    public static function determine(Request $request): string
    {
        return self::explicitChoice($request)
            ?? self::cookieLocale($request)
            ?? self::browserLocale($request)
            ?? self::fallback();
    }

    private static function cookieLocale(Request $request): ?string
    {
        if (! self::cookieEnabled()) {
            return null;
        }

        $cookie = $request->cookie('laradocs_locale');
        $available = self::available();

        return is_string($cookie) && $cookie !== '' && array_key_exists($cookie, $available)
            ? $cookie
            : null;
    }

    private static function browserLocale(Request $request): ?string
    {
        if (! Config::bool('laradocs.locale.detect_browser', true)) {
            return null;
        }

        return self::fromAcceptLanguage($request->header('Accept-Language', ''), self::available());
    }

    /**
     * Parse an `Accept-Language` header and return the highest-quality code
     * that matches an available locale, or null if none match.
     *
     * Matching is attempted in two passes so a header like `fr-CA, fr;q=0.9`
     * can match an available `fr` locale even when `fr-CA` is absent:
     *   1. Exact match against the full tag (e.g. "fr-CA" → "fr-CA").
     *   2. Primary-subtag match — the language prefix before the first `-`
     *      (e.g. "fr-CA" → "fr").
     *
     * @param  array<string, string>  $available
     */
    public static function fromAcceptLanguage(string $header, array $available): ?string
    {
        if ($header === '' || $available === []) {
            return null;
        }

        $tags = self::parseAcceptLanguageTags($header);

        return self::matchExactTag($tags, $available)
            ?? self::matchPrimaryTag($tags, $available);
    }

    /**
     * Parse "fr-CA;q=0.9, en;q=0.8" into [['tag' => 'fr-CA', 'q' => 0.9], …]
     * sorted descending by quality weight.
     *
     * @return array<int, array{tag: string, q: float}>
     */
    private static function parseAcceptLanguageTags(string $header): array
    {
        $tags = [];

        foreach (explode(',', $header) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $segments = explode(';', $part);
            $tag = trim($segments[0]);
            $q = 1.0;

            foreach (array_slice($segments, 1) as $param) {
                $param = trim($param);
                if (str_starts_with($param, 'q=')) {
                    $q = (float) substr($param, 2);

                    break;
                }
            }

            if ($tag !== '' && $tag !== '*') {
                $tags[] = ['tag' => $tag, 'q' => $q];
            }
        }

        usort($tags, fn (array $a, array $b): int => $b['q'] <=> $a['q']);

        return $tags;
    }

    /**
     * Pass 1 — exact match (handles "de" → "de", "zh-TW" → "zh-TW").
     *
     * @param  array<int, array{tag: string, q: float}>  $tags
     * @param  array<string, string>  $available
     */
    private static function matchExactTag(array $tags, array $available): ?string
    {
        foreach ($tags as ['tag' => $tag]) {
            if (array_key_exists($tag, $available)) {
                return $tag;
            }
        }

        return null;
    }

    /**
     * Pass 2 — primary-subtag match (handles "fr-CA" → "fr").
     *
     * @param  array<int, array{tag: string, q: float}>  $tags
     * @param  array<string, string>  $available
     */
    private static function matchPrimaryTag(array $tags, array $available): ?string
    {
        foreach ($tags as ['tag' => $tag]) {
            $primary = explode('-', $tag)[0];

            if ($primary !== $tag && array_key_exists($primary, $available)) {
                return $primary;
            }
        }

        return null;
    }

    /**
     * Scan the published lang directory for locale sub-directories.
     *
     * @return array<string, string>
     */
    private static function scan(): array
    {
        $path = app()->langPath(self::PUBLISHED);

        if (! is_dir($path)) {
            return [];
        }

        $locales = [];

        foreach (array_diff((array) scandir($path), ['.', '..']) as $entry) {
            $entry = (string) $entry;

            if (! is_dir("{$path}/{$entry}")) {
                continue;
            }

            $locales[$entry] = self::label($entry, "{$path}/{$entry}");
        }

        return $locales;
    }

    /**
     * The human-readable label for a locale directory.
     *
     * Reads `meta.php` from the directory when it exists and exposes a string
     * `label` key; otherwise the locale code itself is used as the label.
     */
    private static function label(string $code, string $dir): string
    {
        $meta = "{$dir}/meta.php";

        if (is_file($meta)) {
            /** @var mixed $data */
            $data = require $meta;

            if (is_array($data) && isset($data['label']) && is_string($data['label'])) {
                return $data['label'];
            }
        }

        return $code;
    }
}

<?php

declare(strict_types=1);

namespace Laradocs\Support;

use Illuminate\Http\Request;

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
     * An explicit choice — the `?lang=` query parameter or the cookie it sets —
     * wins when it maps to a detected `available` locale; otherwise the
     * {@see self::fallback()} is used. Unknown codes are ignored so the query
     * string can never force an untranslated locale.
     */
    public static function determine(Request $request): string
    {
        $explicit = self::explicitChoice($request);
        if ($explicit !== null) {
            return $explicit;
        }

        $available = self::available();
        $cookie = $request->cookie('laradocs_locale');
        if (is_string($cookie) && $cookie !== '' && array_key_exists($cookie, $available)) {
            return $cookie;
        }

        return self::fallback();
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

<?php

declare(strict_types=1);

namespace Laradocs\Support;

final class Url
{
    /**
     * Allowed URL schemes for author-supplied links in macros.
     *
     * @var array<int, string>
     */
    private const SAFE_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * Control characters a browser discards while parsing a URL: tab, LF and CR
     * are removed from anywhere in the value, and the remaining C0 controls (plus
     * DEL) are stripped from its ends.
     *
     * They have to go before the scheme is inspected, not after. `trim()` only
     * touches the ends and only knows about " \t\n\r\0\x0B", so without this both
     * "jav<TAB>ascript:alert(1)" and "<0x01>javascript:alert(1)" fail the scheme
     * pattern below, fall through as "relative", and are then reassembled into a
     * working `javascript:` URL by the browser.
     */
    private const IGNORED_BY_BROWSERS = '/[\x00-\x1F\x7F]/';

    /**
     * Return the URL if it uses a safe scheme (or is relative), else '#'.
     * Guards against javascript:, data:, vbscript: and similar vectors.
     *
     * The value returned is the normalised one — stripped of the characters the
     * browser would have ignored — so what is inspected here is exactly what the
     * browser will act on.
     */
    public static function safe(string $url): string
    {
        $trimmed = trim((string) preg_replace(self::IGNORED_BY_BROWSERS, '', $url));

        if ($trimmed === '') {
            return '#';
        }

        // Relative URLs, anchors and root-relative paths are always safe.
        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $trimmed) !== 1) {
            return $trimmed;
        }

        $scheme = strtolower((string) parse_url($trimmed, PHP_URL_SCHEME));

        return in_array($scheme, self::SAFE_SCHEMES, true) ? $trimmed : '#';
    }
}

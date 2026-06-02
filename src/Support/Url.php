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
     * Return the URL if it uses a safe scheme (or is relative), else '#'.
     * Guards against javascript:, data:, vbscript: and similar vectors.
     */
    public static function safe(string $url): string
    {
        $trimmed = trim($url);

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

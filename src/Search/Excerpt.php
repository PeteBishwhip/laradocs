<?php

declare(strict_types=1);

namespace Laradocs\Search;

use Illuminate\Support\Str;

/**
 * Generates a short body snippet centred on the first query term.
 */
final class Excerpt
{
    private const LENGTH = 160;

    public static function make(string $content, string $query): string
    {
        if ($content === '') {
            return '';
        }

        $term = self::firstTerm($query);
        $position = $term === '' ? false : mb_stripos($content, $term);

        if ($position === false) {
            return Str::limit($content, self::LENGTH);
        }

        $start = max(0, $position - 40);
        $snippet = trim(mb_substr($content, $start, self::LENGTH));

        $prefix = $start > 0 ? '…' : '';
        $suffix = $start + self::LENGTH < mb_strlen($content) ? '…' : '';

        return $prefix . $snippet . $suffix;
    }

    private static function firstTerm(string $query): string
    {
        $terms = preg_split('/\s+/u', $query) ?: [];

        return (string) ($terms[0] ?? '');
    }
}

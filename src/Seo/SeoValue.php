<?php

declare(strict_types=1);

namespace Laradocs\Seo;

/**
 * Coerces raw front-matter values into the shapes the SEO layer needs.
 *
 * Extracted from {@see SeoFactory} so that class stays focused on assembling
 * the SEO payload rather than on value normalisation.
 */
final class SeoValue
{
    /**
     * Normalise a value to a non-empty string, or null. Blank strings collapse
     * to null; numbers are stringified; anything else is null.
     */
    public static function asString(mixed $value): ?string
    {
        if (is_string($value)) {
            return trim($value) === '' ? null : $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }

    /**
     * Interpret a front-matter value as a boolean, treating the strings "1",
     * "true", "yes" and "on" (case-insensitive) as true.
     */
    public static function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    /**
     * Case-insensitive, whitespace-trimmed string equality.
     */
    public static function same(string $a, string $b): bool
    {
        return mb_strtolower(trim($a)) === mb_strtolower(trim($b));
    }
}

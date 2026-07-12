<?php

declare(strict_types=1);

namespace Laradocs\Deploy;

/**
 * Typed coercion over the `mixed` values that come back from JSON responses
 * and on-disk credential files, so the deploy classes work with concrete
 * types instead of `mixed`.
 */
final class Json
{
    /**
     * Coerce a decoded JSON value into a string-keyed array (an object).
     *
     * @return array<string, mixed>
     * @param mixed $value
     */
    public static function object($value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $key => $item) {
            $out[(string) $key] = $item;
        }

        return $out;
    }

    /**
     * @param mixed $value
     */
    public static function string($value, string $default = ''): string
    {
        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @param mixed $value
     */
    public static function nullableString($value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @param mixed $value
     */
    public static function int($value, int $default = 0): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }
}

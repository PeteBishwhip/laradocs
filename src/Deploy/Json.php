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
     */
    public static function object(mixed $value): array
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

    public static function string(mixed $value, string $default = ''): string
    {
        return is_scalar($value) ? (string) $value : $default;
    }

    public static function nullableString(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    public static function int(mixed $value, int $default = 0): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }
}

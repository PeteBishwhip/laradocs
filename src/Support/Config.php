<?php

declare(strict_types=1);

namespace Laradocs\Support;

/**
 * Typed accessors over Laravel's config repository so the rest of the package
 * works with concrete types instead of `mixed`.
 */
final class Config
{
    public static function string(string $key, string $default = ''): string
    {
        $value = config($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    public static function nullableString(string $key): ?string
    {
        $value = config($key);

        return is_scalar($value) ? (string) $value : null;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = config($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public static function nullableInt(string $key): ?int
    {
        $value = config($key);

        return is_numeric($value) ? (int) $value : null;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        return (bool) config($key, $default);
    }

    /**
     * @param  array<array-key, mixed>  $default
     * @return array<array-key, mixed>
     */
    public static function array(string $key, array $default = []): array
    {
        $value = config($key, $default);

        return is_array($value) ? $value : $default;
    }
}

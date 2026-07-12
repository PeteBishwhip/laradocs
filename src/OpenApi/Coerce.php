<?php

declare(strict_types=1);

namespace Laradocs\OpenApi;

/**
 * Internal helpers for narrowing the `mixed` values that fall out of a parsed
 * spec / a cache payload into the concrete shapes the value objects expect.
 * Keeps the normalisation code static-analysis clean without sprinkling
 * `is_scalar()`/`@var` checks across every call site.
 *
 * @internal
 */
final class Coerce
{
    /**
     * @param mixed $value
     */
    public static function string($value): string
    {
        return is_scalar($value) ? (string) $value : '';
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
    public static function bool($value): bool
    {
        return (bool) (is_scalar($value) ? $value : false);
    }

    /**
     * @return array<string, mixed>
     * @param mixed $value
     */
    public static function assoc($value): array
    {
        if (! is_array($value)) {
            return [];
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @return array<int, array<string, mixed>>
     * @param mixed $value
     */
    public static function listOfAssoc($value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];

        foreach ($value as $item) {
            $out[] = self::assoc($item);
        }

        return $out;
    }

    /**
     * @return array<int, string>
     * @param mixed $value
     */
    public static function stringList($value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];

        foreach ($value as $item) {
            if (is_scalar($item)) {
                $out[] = (string) $item;
            }
        }

        return $out;
    }
}

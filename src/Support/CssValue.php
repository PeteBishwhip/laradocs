<?php

declare(strict_types=1);

namespace Laradocs\Support;

final class CssValue
{
    public static function customProperty(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            return null;
        }

        if (preg_match('/[{};<>]/', $value) === 1) {
            return null;
        }

        if (str_contains($value, '/*') || str_contains($value, '*/')) {
            return null;
        }

        return $value;
    }
}

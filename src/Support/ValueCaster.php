<?php

declare(strict_types=1);

namespace Laradocs\Support;

/**
 * Coerces raw textual argument values into PHP scalars. Shared by the macro
 * (`@docs(...)`) and Blade-component (`<x-...>`) syntaxes so equivalent calls
 * resolve to identical arguments — the two front-ends round-trip cleanly.
 */
final class ValueCaster
{
    /**
     * `true`/`false` become booleans, numeric strings become int/float, and
     * everything else is returned as an unquoted string.
     */
    public static function cast(string $value): mixed
    {
        $value = trim($value);

        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        if (is_numeric($value)) {
            return $value + 0;
        }

        return self::unquote($value);
    }

    /**
     * Strip a single matching pair of surrounding quotes, if present.
     */
    public static function unquote(string $value): string
    {
        $value = trim($value);

        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];

            if (($first === '"' || $first === "'") && $first === $last) {
                return substr($value, 1, -1);
            }
        }

        return $value;
    }
}

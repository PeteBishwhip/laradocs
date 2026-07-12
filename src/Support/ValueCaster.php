<?php

declare(strict_types=1);

namespace Laradocs\Support;

/**
 * Coerces raw textual argument values into PHP scalars. Shared by the macro
 * (`@docs(...)`), Blade-component (`<x-...>`), and icon (`@icon(...)`) syntaxes
 * so equivalent calls resolve to identical arguments.
 */
final class ValueCaster
{
    /**
     * `true`/`false` become booleans, numeric strings become int/float, and
     * everything else is returned as an unquoted string.
     * @return mixed
     */
    public static function cast(string $value)
    {
        $value = trim($value);

        switch (true) {
            case $value === 'true':
                return true;
            case $value === 'false':
                return false;
            case is_numeric($value):
                return $value + 0;
            default:
                return self::unquote($value);
        }
    }

    /**
     * Split a comma-separated argument list on commas, respecting quoted strings.
     * Used by @docs(...), <x-...>, and @icon(...) so the tokeniser is not duplicated.
     *
     * @return array<int, string>
     */
    public static function tokenize(string $inner): array
    {
        $tokens = [];
        $buffer = '';
        $quote = null;
        $len = strlen($inner);

        for ($i = 0; $i < $len; $i++) {
            $char = $inner[$i];

            if ($quote !== null) {
                $buffer .= $char;

                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $buffer .= $char;
            } elseif ($char === ',') {
                $tokens[] = trim($buffer);
                $buffer = '';
            } else {
                $buffer .= $char;
            }
        }

        if (trim($buffer) !== '') {
            $tokens[] = trim($buffer);
        }

        return $tokens;
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
                return (string) substr($value, 1, -1);
            }
        }

        return $value;
    }
}

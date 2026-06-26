<?php

declare(strict_types=1);

namespace Laradocs\OpenApi\Generator;

use ReflectionMethod;

/**
 * Reads the raw PHP source of a reflected method.
 *
 * Both {@see RequestInspector} (scraping inline `validate([...])` calls) and
 * {@see ResponseInspector} (scraping a resource's `toArray()` keys) fall back to
 * source inspection when reflection alone cannot recover a schema. This shared
 * helper isolates that file read: it returns the method body's source, or null
 * for methods with no readable source file (e.g. internal/built-in methods,
 * whose `getFileName()` is `false`).
 */
final class MethodSource
{
    public static function read(ReflectionMethod $method): ?string
    {
        $file = $method->getFileName();
        $start = $method->getStartLine();
        $end = $method->getEndLine();

        if ($file === false || $start === false || $end === false || ! is_file($file)) {
            return null;
        }

        $lines = file($file) ?: [];

        return implode('', array_slice($lines, $start - 1, $end - $start + 1));
    }
}

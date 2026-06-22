<?php

declare(strict_types=1);

namespace Laradocs\Icons;

/**
 * Thin static facade over IconRegistry for use inside Blade views via @use.
 */
final class Icon
{
    public static function render(?string $name, string $variant = 'outline', ?string $set = null): string
    {
        if ($name === null || $name === '') {
            return '';
        }

        return app(IconRegistry::class)->render($name, $variant, $set);
    }
}

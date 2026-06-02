<?php

declare(strict_types=1);

namespace Laradocs\Exceptions;

use InvalidArgumentException;

final class UnknownMacroException extends InvalidArgumentException
{
    public static function for(string $name): self
    {
        return new self("No laradocs macro registered with the name [{$name}].");
    }
}

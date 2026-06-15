<?php

declare(strict_types=1);

namespace Laradocs\Deploy;

use RuntimeException;

final class NotAuthenticatedException extends RuntimeException
{
    public static function make(): self
    {
        return new self('Not authenticated. Run `php artisan laradocs:login` first.');
    }
}

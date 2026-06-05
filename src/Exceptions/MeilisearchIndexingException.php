<?php

declare(strict_types=1);

namespace Laradocs\Exceptions;

use RuntimeException;

final class MeilisearchIndexingException extends RuntimeException
{
    /**
     * @param  array<int, string>  $messages  Formatted "<error> [<type>]" entries from the failed task batch.
     */
    public static function rejectedTasks(array $messages): self
    {
        return new self('Meilisearch rejected indexing tasks: ' . implode('; ', $messages));
    }
}

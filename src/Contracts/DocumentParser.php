<?php

declare(strict_types=1);

namespace Laradocs\Contracts;

interface DocumentParser
{
    /**
     * Convert a markdown string into rendered HTML.
     */
    public function parse(string $markdown): string;
}

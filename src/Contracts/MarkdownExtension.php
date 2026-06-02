<?php

declare(strict_types=1);

namespace Laradocs\Contracts;

interface MarkdownExtension
{
    /**
     * Transform raw markdown before it is converted to HTML.
     */
    public function processMarkdown(string $markdown): string;
}

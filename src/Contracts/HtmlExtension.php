<?php

declare(strict_types=1);

namespace Laradocs\Contracts;

interface HtmlExtension
{
    /**
     * Transform rendered HTML after markdown conversion.
     */
    public function processHtml(string $html): string;
}

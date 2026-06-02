<?php

declare(strict_types=1);

namespace Laradocs\Contracts;

interface MetadataResolver
{
    /**
     * Split raw file contents into [front-matter array, body markdown].
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    public function resolve(string $raw): array;
}

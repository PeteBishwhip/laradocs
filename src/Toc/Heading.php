<?php

declare(strict_types=1);

namespace Laradocs\Toc;

final class Heading
{
    public function __construct(
        public readonly int $level,
        public readonly string $id,
        public readonly string $text,
    ) {}
}

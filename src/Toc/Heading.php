<?php

declare(strict_types=1);

namespace Laradocs\Toc;

final class Heading
{
    /**
     * @readonly
     * @var int
     */
    public $level;
    /**
     * @readonly
     * @var string
     */
    public $id;
    /**
     * @readonly
     * @var string
     */
    public $text;
    public function __construct(int $level, string $id, string $text)
    {
        $this->level = $level;
        $this->id = $id;
        $this->text = $text;
    }
}

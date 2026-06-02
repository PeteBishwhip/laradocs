<?php

declare(strict_types=1);

namespace Laradocs\Toc;

use DOMElement;
use DOMXPath;
use Laradocs\Support\Html;

final class TableOfContents
{
    /**
     * @param  array<int, Heading>  $headings
     */
    public function __construct(
        public readonly array $headings = [],
    ) {}

    public static function fromHtml(string $html, int $minLevel = 2, int $maxLevel = 3): self
    {
        if (trim($html) === '') {
            return new self;
        }

        $dom = Html::load($html);
        $xpath = new DOMXPath($dom);

        $conditions = [];
        for ($level = $minLevel; $level <= $maxLevel; $level++) {
            $conditions[] = "self::h{$level}";
        }

        $nodes = $xpath->query('//*[' . implode(' or ', $conditions) . ']');
        $headings = [];

        if ($nodes !== false) {
            /** @var DOMElement $node */
            foreach ($nodes as $node) {
                $id = $node->getAttribute('id');

                if ($id === '') {
                    continue;
                }

                $headings[] = new Heading(
                    level: (int) substr($node->nodeName, 1),
                    id: $id,
                    text: trim(ltrim($node->textContent, '#')),
                );
            }
        }

        return new self($headings);
    }

    public function isEmpty(): bool
    {
        return $this->headings === [];
    }

    public function count(): int
    {
        return count($this->headings);
    }
}

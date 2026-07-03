<?php

declare(strict_types=1);

namespace Laradocs\Documents;

/**
 * Immutable node in the assembled navigation tree.
 *
 * @psalm-immutable
 */
final class TreeNode
{
    /**
     * @param  array<int, TreeNode>  $children
     */
    public function __construct(
        public readonly string $title,
        public readonly string $slug,
        public readonly ?Document $document = null,
        public readonly array $children = [],
        public readonly int $depth = 1,
    ) {}

    public function isSection(): bool
    {
        return $this->children !== [];
    }

    public function isLink(): bool
    {
        return $this->document !== null;
    }

    public function order(): int
    {
        return $this->document?->order() ?? PHP_INT_MAX;
    }

    public function group(): ?string
    {
        return $this->document?->group();
    }

    public function isHidden(): bool
    {
        return $this->document?->isHidden() ?? false;
    }

    /**
     * Return a pruned clone excluding hidden documents and empty sections.
     */
    public function pruned(): ?TreeNode
    {
        $children = [];

        foreach ($this->children as $child) {
            if (($prunedChild = $child->pruned()) !== null) {
                $children[] = $prunedChild;
            }
        }

        // Drop a node with no surviving children that is either hidden or a
        // pure section placeholder. Sections keep showing while any child does.
        if ($children === [] && ($this->isHidden() || ! $this->isLink())) {
            return null;
        }

        return new self($this->title, $this->slug, $this->document, $children, $this->depth);
    }
}

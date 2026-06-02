<?php

declare(strict_types=1);

namespace Laradocs\Documents;

final class TreeNode
{
    /**
     * @param  array<int, TreeNode>  $children
     */
    public function __construct(
        public string $title,
        public string $slug,
        public ?Document $document = null,
        public array $children = [],
        public int $depth = 1,
    ) {}

    public function addChild(TreeNode $child): void
    {
        $this->children[] = $child;
    }

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
        return $this->document?->order() ?? 999;
    }

    public function group(): ?string
    {
        return $this->document?->group();
    }

    public function isHidden(): bool
    {
        return $this->document?->isHidden() ?? false;
    }

    public function sortChildren(): void
    {
        usort($this->children, function (TreeNode $a, TreeNode $b): int {
            return [$a->order(), strtolower($a->title)] <=> [$b->order(), strtolower($b->title)];
        });

        foreach ($this->children as $child) {
            $child->sortChildren();
        }
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

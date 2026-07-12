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
     * @readonly
     * @var string
     */
    public $title;
    /**
     * @readonly
     * @var string
     */
    public $slug;
    /**
     * @readonly
     * @var \Laradocs\Documents\Document|null
     */
    public $document;
    /**
     * @var array<int, TreeNode>
     * @readonly
     */
    public $children = [];
    /**
     * @readonly
     * @var int
     */
    public $depth = 1;
    /**
     * @param  array<int, TreeNode>  $children
     */
    public function __construct(string $title, string $slug, ?Document $document = null, array $children = [], int $depth = 1)
    {
        $this->title = $title;
        $this->slug = $slug;
        $this->document = $document;
        $this->children = $children;
        $this->depth = $depth;
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
        return (($nullsafeVariable1 = $this->document) ? $nullsafeVariable1->order() : null) ?? PHP_INT_MAX;
    }

    public function group(): ?string
    {
        return ($nullsafeVariable2 = $this->document) ? $nullsafeVariable2->group() : null;
    }

    public function isHidden(): bool
    {
        return (($nullsafeVariable3 = $this->document) ? $nullsafeVariable3->isHidden() : null) ?? false;
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

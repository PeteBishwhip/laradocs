<?php

declare(strict_types=1);

namespace Laradocs\Support;

use Laradocs\Documents\TreeNode;

/**
 * Derives breadcrumbs and previous/next links from a navigation tree.
 */
final class Navigation
{
    /**
     * Flatten the tree into an ordered list of linkable nodes (depth-first).
     *
     * @param  array<int, TreeNode>  $nodes
     * @return array<int, TreeNode>
     */
    public static function flatten(array $nodes): array
    {
        $flat = [];

        foreach ($nodes as $node) {
            if ($node->isLink()) {
                $flat[] = $node;
            }

            $flat = array_merge($flat, self::flatten($node->children));
        }

        return $flat;
    }

    /**
     * The chain of nodes from a root down to the node matching $slug.
     *
     * @param  array<int, TreeNode>  $nodes
     * @return array<int, TreeNode>
     */
    public static function breadcrumbs(array $nodes, string $slug): array
    {
        foreach ($nodes as $node) {
            if ($node->slug === $slug) {
                return [$node];
            }

            $trail = self::breadcrumbs($node->children, $slug);

            if ($trail !== []) {
                return array_merge([$node], $trail);
            }
        }

        return [];
    }

    /**
     * Return [previous, next] linkable nodes around the given slug.
     *
     * @param  array<int, TreeNode>  $nodes
     * @return array{0: TreeNode|null, 1: TreeNode|null}
     */
    public static function siblings(array $nodes, string $slug): array
    {
        $flat = self::flatten($nodes);

        foreach ($flat as $i => $node) {
            if ($node->slug === $slug) {
                return [$flat[$i - 1] ?? null, $flat[$i + 1] ?? null];
            }
        }

        return [null, null];
    }
}

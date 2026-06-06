<?php

declare(strict_types=1);

namespace Laradocs\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Laradocs\Documents\TreeNode;
use Laradocs\Laradocs;
use Laradocs\Routing\DocumentUrl;

final class ApiTreeController
{
    public function __construct(
        private readonly Laradocs $laradocs,
    ) {}

    public function __invoke(): JsonResponse
    {
        $roots = $this->laradocs->tree()->navigation();
        $included = $this->collectIncluded($roots);

        $body = [
            'jsonapi' => ['version' => '1.0'],
            'links' => ['self' => DocumentUrl::apiTree()],
            'data' => array_map($this->serializeNode(...), $roots),
        ];

        if ($included !== []) {
            $body['included'] = $included;
        }

        return (new JsonResponse($body))
            ->header('Content-Type', 'application/vnd.api+json');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeNode(TreeNode $node): array
    {
        $resource = [
            'type' => 'node',
            'id' => $node->slug,
            'attributes' => [
                'title' => $node->title,
                'slug' => $node->slug,
                'url' => $node->isLink() ? DocumentUrl::toSlug($node->slug) : null,
            ],
            'relationships' => [
                'children' => [
                    'data' => array_map(
                        fn (TreeNode $child): array => ['type' => 'node', 'id' => $child->slug],
                        $node->children,
                    ),
                ],
            ],
        ];

        return $resource;
    }

    /**
     * All non-root nodes, collected depth-first for the compound document.
     *
     * @param  array<int, TreeNode>  $roots
     * @return array<int, array<string, mixed>>
     */
    private function collectIncluded(array $roots): array
    {
        $acc = [];

        foreach ($roots as $root) {
            $this->collectDescendants($root->children, $acc);
        }

        return array_values($acc);
    }

    /**
     * @param  array<int, TreeNode>  $nodes
     * @param  array<string, array<string, mixed>>  $acc
     */
    private function collectDescendants(array $nodes, array &$acc): void
    {
        foreach ($nodes as $node) {
            $acc[$node->slug] = $this->serializeNode($node);
            $this->collectDescendants($node->children, $acc);
        }
    }
}

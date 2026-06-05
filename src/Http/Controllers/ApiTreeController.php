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
        $nodes = $this->laradocs->tree()->navigation();

        return new JsonResponse([
            'version' => 1,
            'data' => array_map($this->serializeNode(...), $nodes),
        ]);
    }

    /**
     * @return array{title: string, slug: string, url: string|null, children: array<int, mixed>}
     */
    private function serializeNode(TreeNode $node): array
    {
        return [
            'title' => $node->title,
            'slug' => $node->slug,
            'url' => $node->isLink() ? DocumentUrl::toSlug($node->slug) : null,
            'children' => array_map($this->serializeNode(...), $node->children),
        ];
    }
}

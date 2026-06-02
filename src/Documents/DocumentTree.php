<?php

declare(strict_types=1);

namespace Laradocs\Documents;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class DocumentTree
{
    /**
     * @param  array<int, TreeNode>  $roots
     */
    public function __construct(
        public readonly array $roots = [],
        public readonly ?Document $rootDocument = null,
    ) {}

    /**
     * Assemble a multi-level tree from a flat collection of documents.
     *
     * @param  DocumentCollection<int, Document>  $documents
     */
    public static function fromDocuments(DocumentCollection $documents, string $indexName = '_index'): self
    {
        /** @var array<string, TreeNode> $index */
        $index = [];
        /** @var array<int, TreeNode> $roots */
        $roots = [];
        $rootDocument = null;

        foreach ($documents as $document) {
            $segments = $document->segments();

            if ($segments === []) {
                $rootDocument = $document;

                continue;
            }

            if (self::isIndexFile($document, $indexName)) {
                $node = self::ensureSection($segments, $index, $roots);
                $node->document = $document;
                $node->title = $document->title();

                continue;
            }

            $slug = $document->slug;

            if (isset($index[$slug])) {
                // A section already exists at this slug — attach the document.
                $index[$slug]->document = $document;
                $index[$slug]->title = $document->title();

                continue;
            }

            $leaf = new TreeNode($document->title(), $slug, $document, depth: count($segments));
            $index[$slug] = $leaf;

            $parentSegments = array_slice($segments, 0, -1);

            if ($parentSegments === []) {
                $roots[] = $leaf;
            } else {
                self::ensureSection($parentSegments, $index, $roots)->addChild($leaf);
            }
        }

        foreach ($roots as $root) {
            $root->sortChildren();
        }

        usort($roots, function (TreeNode $a, TreeNode $b): int {
            return [$a->order(), strtolower($a->title)] <=> [$b->order(), strtolower($b->title)];
        });

        return new self($roots, $rootDocument);
    }

    /**
     * Navigation tree with hidden documents and empty sections removed.
     *
     * @return array<int, TreeNode>
     */
    public function navigation(): array
    {
        $nodes = [];

        foreach ($this->roots as $root) {
            if (($pruned = $root->pruned()) !== null) {
                $nodes[] = $pruned;
            }
        }

        return $nodes;
    }

    /**
     * Top-level navigation nodes bucketed by their group metadata.
     *
     * @return Collection<string, array<int, TreeNode>>
     */
    public function grouped(): Collection
    {
        return Collection::make($this->navigation())
            ->groupBy(fn (TreeNode $node): string => $node->group() ?? '')
            ->map(fn (Collection $nodes): array => $nodes->all());
    }

    /**
     * Find or lazily create the section node for a path of segments.
     *
     * @param  array<int, string>  $segments
     * @param  array<string, TreeNode>  $index
     * @param  array<int, TreeNode>  $roots
     */
    private static function ensureSection(array $segments, array &$index, array &$roots): TreeNode
    {
        $slug = implode('/', $segments);

        if (isset($index[$slug])) {
            return $index[$slug];
        }

        $last = $segments[count($segments) - 1];

        $node = new TreeNode(
            title: Str::of($last)->replace('-', ' ')->title()->toString(),
            slug: $slug,
            depth: count($segments),
        );
        $index[$slug] = $node;

        $parentSegments = array_slice($segments, 0, -1);

        if ($parentSegments === []) {
            $roots[] = $node;
        } else {
            self::ensureSection($parentSegments, $index, $roots)->addChild($node);
        }

        return $node;
    }

    private static function isIndexFile(Document $document, string $indexName): bool
    {
        $basename = basename($document->relativePath);

        return Str::beforeLast($basename, '.') === $indexName;
    }
}

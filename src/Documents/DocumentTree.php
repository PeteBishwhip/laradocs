<?php

declare(strict_types=1);

namespace Laradocs\Documents;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @psalm-immutable
 */
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
     */
    public static function fromDocuments(DocumentCollection $documents, string $indexName = '_index'): self
    {
        /** @var array<string, array{title: string, document: Document|null, depth: int}> $index */
        $index = [];
        /** @var array<string, array<int, string>> $childSlugs */
        $childSlugs = [];
        /** @var array<int, string> $rootSlugs */
        $rootSlugs = [];
        $rootDocument = null;

        foreach ($documents as $document) {
            $segments = $document->segments();

            if ($segments === []) {
                $rootDocument = $document;

                continue;
            }

            if (self::isIndexFile($document, $indexName)) {
                $slug = self::ensureSection($segments, $index, $childSlugs, $rootSlugs);
                $index[$slug] = ['title' => $document->title(), 'document' => $document, 'depth' => $index[$slug]['depth']];

                continue;
            }

            $slug = $document->slug;

            if (isset($index[$slug])) {
                // A section already exists at this slug — attach the document.
                $index[$slug] = ['title' => $document->title(), 'document' => $document, 'depth' => $index[$slug]['depth']];

                continue;
            }

            $index[$slug] = ['title' => $document->title(), 'document' => $document, 'depth' => count($segments)];

            $parentSegments = array_slice($segments, 0, -1);

            if ($parentSegments === []) {
                $rootSlugs[] = $slug;
            } else {
                $childSlugs[self::ensureSection($parentSegments, $index, $childSlugs, $rootSlugs)][] = $slug;
            }
        }

        $roots = array_map(
            fn (string $slug): TreeNode => self::buildNode($slug, $index, $childSlugs),
            $rootSlugs
        );

        usort($roots, self::compareNodes(...));

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
     * Illuminate's Collection isn't annotated for Psalm purity upstream, but
     * `make`/`groupBy`/`map` only ever build fresh collections here — none of
     * them touch `$this`.
     *
     * @return Collection<string, array<int, TreeNode>>
     *
     * @psalm-suppress ImpureMethodCall
     * @psalm-suppress MixedArgumentTypeCoercion
     */
    public function grouped(): Collection
    {
        return Collection::make($this->navigation())
            ->groupBy(fn (TreeNode $node): string => $node->group() ?? '')
            ->map(fn (Collection $nodes): array => $nodes->all());
    }

    /**
     * Recursively materialise an immutable node (and its children) from the
     * builder state accumulated while walking the document list.
     *
     * @param  array<string, array{title: string, document: Document|null, depth: int}>  $index
     * @param  array<string, array<int, string>>  $childSlugs
     */
    private static function buildNode(string $slug, array $index, array $childSlugs): TreeNode
    {
        $data = $index[$slug];

        $children = array_map(
            fn (string $childSlug): TreeNode => self::buildNode($childSlug, $index, $childSlugs),
            $childSlugs[$slug] ?? []
        );

        usort($children, self::compareNodes(...));

        return new TreeNode($data['title'], $slug, $data['document'], $children, $data['depth']);
    }

    /**
     * Find or lazily create the section entry for a path of segments,
     * returning its slug.
     *
     * @param  array<int, string>  $segments
     * @param  array<string, array{title: string, document: Document|null, depth: int}>  $index
     * @param  array<string, array<int, string>>  $childSlugs
     * @param  array<int, string>  $rootSlugs
     */
    private static function ensureSection(array $segments, array &$index, array &$childSlugs, array &$rootSlugs): string
    {
        $slug = implode('/', $segments);

        if (isset($index[$slug])) {
            return $slug;
        }

        $last = $segments[count($segments) - 1];

        $index[$slug] = [
            'title' => Str::of($last)->replace('-', ' ')->title()->toString(),
            'document' => null,
            'depth' => count($segments),
        ];

        $parentSegments = array_slice($segments, 0, -1);

        if ($parentSegments === []) {
            $rootSlugs[] = $slug;
        } else {
            $childSlugs[self::ensureSection($parentSegments, $index, $childSlugs, $rootSlugs)][] = $slug;
        }

        return $slug;
    }

    private static function compareNodes(TreeNode $a, TreeNode $b): int
    {
        return [$a->order(), strtolower($a->title)] <=> [$b->order(), strtolower($b->title)];
    }

    private static function isIndexFile(Document $document, string $indexName): bool
    {
        $basename = basename($document->relativePath);

        return Str::beforeLast($basename, '.') === $indexName;
    }
}

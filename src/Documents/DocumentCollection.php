<?php

declare(strict_types=1);

namespace Laradocs\Documents;

use Illuminate\Support\Collection;

/**
 * @extends Collection<int, Document>
 */
final class DocumentCollection extends Collection
{
    /**
     * Only documents that should appear in navigation.
     */
    public function visible(): self
    {
        return $this->reject(fn (Document $doc): bool => $doc->isHidden())->values();
    }

    /**
     * Sort by metadata order, then by title.
     */
    public function ordered(): self
    {
        return $this->sort(
            fn (Document $a, Document $b): int => [$a->order(), strtolower($a->title())]
                <=> [$b->order(), strtolower($b->title())]
        )->values();
    }

    /**
     * Group documents by their metadata "group" (ungrouped under "").
     *
     * @return Collection<string, DocumentCollection<int, Document>>
     */
    public function byGroup(): Collection
    {
        /** @var Collection<string, DocumentCollection<int, Document>> $groups */
        $groups = $this->groupBy(fn (Document $doc): string => $doc->group() ?? '');

        return $groups;
    }

    /**
     * Documents carrying the given tag.
     */
    public function byTag(string $tag): self
    {
        return $this->filter(
            fn (Document $doc): bool => in_array($tag, $doc->metadata->tags, true)
        )->values();
    }

    public function findBySlug(string $slug): ?Document
    {
        return $this->first(fn (Document $doc): bool => $doc->slug === $slug);
    }
}

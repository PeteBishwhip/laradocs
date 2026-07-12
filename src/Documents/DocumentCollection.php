<?php

declare(strict_types=1);

namespace Laradocs\Documents;

use Illuminate\Support\Collection;

/**
 * A typed collection of {@see Document} instances.
 *
 * Extends Laravel's Collection, so it remains mutable like any other
 * collection (push, forget, etc. all still work). The domain-specific
 * accessors below (`visible`, `ordered`, `byGroup`, `byTag`, `findBySlug`)
 * never mutate the collection they're called on — each returns a new
 * instance or a scalar/nullable read.
 *
 * @extends Collection<int, Document>
 */
final class DocumentCollection extends Collection
{
    /**
     * Only documents that should appear in navigation.
     */
    public function visible(): self
    {
        return $this->reject(function (Document $doc): bool {
            return $doc->isHidden();
        })->values();
    }

    /**
     * Sort by metadata order, then by title.
     */
    public function ordered(): self
    {
        return $this->sort(
            function (Document $a, Document $b): int {
                return [$a->order(), strtolower($a->title())]
                    <=> [$b->order(), strtolower($b->title())];
            }
        )->values();
    }

    /**
     * Group documents by their metadata "group" (ungrouped under "").
     *
     * @return Collection<string, DocumentCollection>
     */
    public function byGroup(): Collection
    {
        /** @var Collection<string, DocumentCollection> $groups */
        $groups = $this->groupBy(function (Document $doc): string {
            return $doc->group() ?? '';
        });

        return $groups;
    }

    /**
     * Documents carrying the given tag.
     */
    public function byTag(string $tag): self
    {
        return $this->filter(
            function (Document $doc) use ($tag): bool {
                return in_array($tag, $doc->metadata->tags, true);
            }
        )->values();
    }

    public function findBySlug(string $slug): ?Document
    {
        return $this->first(function (Document $doc) use ($slug): bool {
            return $doc->slug === $slug;
        });
    }
}

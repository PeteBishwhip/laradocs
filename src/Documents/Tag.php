<?php

declare(strict_types=1);

namespace Laradocs\Documents;

use Illuminate\Contracts\Support\Arrayable;

/**
 * A single tag and the visible documents that carry it.
 *
 * @implements Arrayable<string, mixed>
 */
final class Tag implements Arrayable
{
    /**
     * @param  DocumentCollection<int, Document>  $documents
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $label,
        public readonly DocumentCollection $documents,
    ) {}

    /**
     * How many documents carry this tag.
     */
    public function count(): int
    {
        return $this->documents->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'label' => $this->label,
            'count' => $this->count(),
            'documents' => $this->documents->map(
                fn (Document $doc): array => [
                    'slug' => $doc->slug,
                    'title' => $doc->title(),
                ]
            )->all(),
        ];
    }
}

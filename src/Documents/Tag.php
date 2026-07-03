<?php

declare(strict_types=1);

namespace Laradocs\Documents;

use Illuminate\Contracts\Support\Arrayable;
use Override;

/**
 * A single tag and the visible documents that carry it.
 *
 * The wrapped {@see DocumentCollection} is a Laravel Collection and thus
 * mutable by construction, but every producer of a Tag (see {@see
 * Laradocs\Laradocs::tags()}) hands it a freshly built, unshared instance.
 * Do not mutate `$documents` in place — treat it as read-only.
 *
 * @implements Arrayable<string, mixed>
 */
final class Tag implements Arrayable
{
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
    #[Override]
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'label' => $this->label,
            'count' => $this->count(),
            'documents' => array_map(
                fn (Document $doc): array => [
                    'slug' => $doc->slug,
                    'title' => $doc->title(),
                ],
                $this->documents->all()
            ),
        ];
    }
}

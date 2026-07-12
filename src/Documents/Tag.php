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
    /**
     * @readonly
     * @var string
     */
    public $slug;
    /**
     * @readonly
     * @var string
     */
    public $label;
    /**
     * @readonly
     * @var \Laradocs\Documents\DocumentCollection
     */
    public $documents;
    public function __construct(string $slug, string $label, DocumentCollection $documents)
    {
        $this->slug = $slug;
        $this->label = $label;
        $this->documents = $documents;
    }

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
            'documents' => array_map(
                function (Document $doc): array {
                    return [
                        'slug' => $doc->slug,
                        'title' => $doc->title(),
                    ];
                },
                $this->documents->all()
            ),
        ];
    }
}

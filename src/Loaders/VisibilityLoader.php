<?php

declare(strict_types=1);

namespace Laradocs\Loaders;

use Closure;
use Laradocs\Contracts\DocumentLoader;
use Laradocs\Contracts\DocumentVisibility;
use Laradocs\Documents\Document;
use Laradocs\Documents\DocumentCollection;

/**
 * Applies a {@see DocumentVisibility} rule to everything the docs read.
 *
 * It wraps the loader rather than sitting higher up because that is the one
 * place every read passes through, `find()` included — filtering further down
 * the line would leave a direct URL serving a page the navigation hides.
 *
 * The read itself is held for the lifetime of the loader, since files do not
 * change mid-request and laradocs asks for every document several times while
 * rendering a page. The rule is asked each time, because only the rule knows
 * what its answer depends on — the signed-in user, a tenant, the time of day.
 * A rule that is expensive should memoise on whatever that is.
 */
final class VisibilityLoader implements DocumentLoader
{
    private ?DocumentCollection $documents = null;

    private bool $filtering = true;

    public function __construct(
        private readonly DocumentLoader $loader,
        private readonly DocumentVisibility $visibility,
    ) {}

    #[\Override]
    public function all(): DocumentCollection
    {
        $documents = $this->documents ??= $this->loader->all();

        return $this->filtering ? $this->visibility->filter($documents) : $documents;
    }

    #[\Override]
    public function find(string $slug): ?Document
    {
        return $this->all()->findBySlug($slug);
    }

    /**
     * Read every document, whatever the visibility rule says.
     *
     * For work that is not being done on a reader's behalf: warming caches, a
     * console command, an export. Anything cached inside the callback is keyed
     * by the document set it was built from, so a reader whose set is smaller
     * still gets their own entry rather than this one.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function withoutFiltering(Closure $callback): mixed
    {
        $filtering = $this->filtering;
        $this->filtering = false;

        try {
            return $callback();
        } finally {
            $this->filtering = $filtering;
        }
    }
}

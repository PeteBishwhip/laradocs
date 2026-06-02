<?php

declare(strict_types=1);

namespace Laradocs\Contracts;

use Laradocs\Documents\Document;
use Laradocs\Documents\DocumentCollection;

interface DocumentLoader
{
    /**
     * Load every document found in the configured source directory.
     *
     * @return DocumentCollection<int, Document>
     */
    public function all(): DocumentCollection;

    /**
     * Find a single document by its resolved slug.
     */
    public function find(string $slug): ?Document;
}

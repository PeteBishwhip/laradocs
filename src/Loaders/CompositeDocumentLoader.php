<?php

declare(strict_types=1);

namespace Laradocs\Loaders;

use Laradocs\Contracts\DocumentLoader;
use Laradocs\Documents\Document;
use Laradocs\Documents\DocumentCollection;

/**
 * Fans a single {@see DocumentLoader} interface out across several underlying
 * loaders, presenting their combined documents as one source.
 *
 * Order is significant: {@see all()} concatenates each loader's documents in
 * registration order, and {@see find()} returns the first loader to resolve a
 * slug. The package wraps `[FilesystemLoader, OpenApiLoader]`, so a hand-written
 * markdown page always wins a slug collision with a generated OpenAPI page — a
 * user `api.md` shadows the OpenAPI overview mounted at the `api` base slug.
 */
final class CompositeDocumentLoader implements DocumentLoader
{
    /**
     * @var array<int, DocumentLoader>
     * @readonly
     */
    private $loaders;
    /**
     * @param  array<int, DocumentLoader>  $loaders  Consulted in order; earlier
     *                                               loaders win slug collisions in {@see find()}.
     */
    public function __construct(array $loaders)
    {
        $this->loaders = $loaders;
    }

    public function all(): DocumentCollection
    {
        $documents = new DocumentCollection;

        foreach ($this->loaders as $loader) {
            foreach ($loader->all() as $document) {
                $documents->push($document);
            }
        }

        return $documents;
    }

    public function find(string $slug): ?Document
    {
        foreach ($this->loaders as $loader) {
            $document = $loader->find($slug);

            if ($document !== null) {
                return $document;
            }
        }

        return null;
    }
}

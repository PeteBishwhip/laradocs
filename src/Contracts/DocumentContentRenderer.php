<?php

declare(strict_types=1);

namespace Laradocs\Contracts;

use Laradocs\Documents\Document;
use Laradocs\Laradocs;

/**
 * Extension point for rendering a document's body to HTML.
 *
 * Implementations are consulted at the single HTML choke-point in
 * {@see Laradocs::render()}: the first renderer whose
 * {@see supports()} returns true produces the HTML, otherwise the package
 * falls back to parsing the document's markdown. This lets native renderers
 * (e.g. OpenAPI) be plugged in without touching markdown behavior.
 */
interface DocumentContentRenderer
{
    /**
     * Whether this renderer should handle the given document.
     */
    public function supports(Document $document): bool;

    /**
     * Render the given document to HTML.
     */
    public function render(Document $document): string;
}

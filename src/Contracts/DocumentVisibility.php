<?php

declare(strict_types=1);

namespace Laradocs\Contracts;

use Laradocs\Documents\Document;
use Laradocs\Documents\DocumentCollection;

/**
 * Decides which documents the current reader may see.
 *
 * Bind an implementation and every read path honours it: the navigation tree,
 * search, the sitemap, feeds, llms.txt, the MCP tools and a direct hit on a
 * page's URL, which 404s rather than rendering. Nothing is bound by default, so
 * a site that does not need this pays nothing.
 *
 * The whole collection is passed rather than one document at a time, because a
 * rule is often about more than the page in front of you — a permission on a
 * section's index page applying to everything beneath it, say. Returning the
 * collection unchanged is a valid answer.
 *
 * @see https://laradocs.dev/docs/advanced/visibility
 */
interface DocumentVisibility
{
    public function filter(DocumentCollection $documents): DocumentCollection;
}

<?php

declare(strict_types=1);

namespace Laradocs\Routing;

use Laradocs\Documents\Document;
use Laradocs\Documents\DocumentTree;
use Laradocs\Documents\TreeNode;

/**
 * Renders a sitemaps.org-compliant XML document from a document tree.
 *
 * Pages are emitted in tree order; hidden pages and pages that redirect
 * elsewhere are skipped (a sitemap should advertise canonical destinations,
 * not interstitials).
 */
final class SitemapBuilder
{
    public function build(DocumentTree $tree): string
    {
        $urls = [];

        if ($tree->rootDocument !== null && $this->includes($tree->rootDocument)) {
            $urls[] = $this->urlFor($tree->rootDocument, depth: 0);
        }

        $this->collect($tree->navigation(), $urls);

        return $this->render($urls);
    }

    /**
     * @param  array<int, TreeNode>  $nodes
     * @param  array<int, string>  $urls
     */
    private function collect(array $nodes, array &$urls): void
    {
        foreach ($nodes as $node) {
            $document = $node->document;

            if ($document !== null && $this->includes($document)) {
                $urls[] = $this->urlFor($document, $node->depth);
            }

            $this->collect($node->children, $urls);
        }
    }

    private function includes(Document $document): bool
    {
        return ! $document->isHidden() && $document->redirect() === null;
    }

    private function urlFor(Document $document, int $depth): string
    {
        $loc = DocumentUrl::toSlug($document->slug);
        $lastmod = $this->lastmod($document);
        $priority = $this->priority($document, $depth);

        $xml = '  <url>' . "\n";
        $xml .= '    <loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>' . "\n";

        if ($lastmod !== null) {
            $xml .= '    <lastmod>' . $lastmod . '</lastmod>' . "\n";
        }

        $xml .= '    <priority>' . number_format($priority, 1, '.', '') . '</priority>' . "\n";
        $xml .= '  </url>' . "\n";

        return $xml;
    }

    private function lastmod(Document $document): ?string
    {
        $declared = $document->metadata->updatedAt;

        if ($declared !== null && $declared !== '') {
            $timestamp = strtotime($declared);

            if ($timestamp !== false) {
                return date('c', $timestamp);
            }
        }

        return $document->modifiedAt > 0 ? date('c', $document->modifiedAt) : null;
    }

    /**
     * Priority falls off with depth: roots are most important. Explicit
     * front-matter wins when given as a numeric value in [0.0, 1.0].
     */
    private function priority(Document $document, int $depth): float
    {
        $explicit = $document->metadata->get('priority');

        if (is_numeric($explicit)) {
            return max(0.0, min(1.0, (float) $explicit));
        }

        return match (true) {
            $depth <= 0 => 1.0,
            $depth === 1 => 0.8,
            $depth === 2 => 0.6,
            default => 0.4,
        };
    }

    /**
     * @param  array<int, string>  $urls
     */
    private function render(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $xml .= implode('', $urls);
        $xml .= '</urlset>' . "\n";

        return $xml;
    }
}

<?php

declare(strict_types=1);

namespace Laradocs\Routing;

use Laradocs\Documents\Document;
use Laradocs\Documents\DocumentTree;
use Laradocs\Documents\TreeNode;
use Laradocs\Support\Config;
use Laradocs\Support\Locale;
use Laradocs\Support\Version;

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
        if ($this->versionExcluded()) {
            return false;
        }

        if ($this->excluded($document)) {
            return false;
        }

        return ! $document->isHidden() && $document->redirect() === null;
    }

    /**
     * Whether the document's slug matches an fnmatch pattern in
     * `seo.sitemap_exclude` — e.g. broadcasting auth or webhook routes that
     * should stay reachable but not be advertised to crawlers.
     */
    private function excluded(Document $document): bool
    {
        /** @var array<int, string> $patterns */
        $patterns = Config::array('laradocs.seo.sitemap_exclude');

        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $document->slug)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the active (non-default) version's pages should be left out of the
     * sitemap. The sitemap is built per active version, so this gates the whole
     * tree: non-default versions are excluded unless `seo.sitemap_all_versions`
     * opts every version in.
     */
    private function versionExcluded(): bool
    {
        if (! Config::bool('laradocs.versions.enabled', false)) {
            return false;
        }

        if (Config::bool('laradocs.seo.sitemap_all_versions', false)) {
            return false;
        }

        $current = Version::current();

        return $current !== null && ! Version::isDefault($current);
    }

    private function urlFor(Document $document, int $depth): string
    {
        $loc = DocumentUrl::toSlug($document->slug);
        $lastmod = $this->lastmod($document);
        $priority = $this->priority($document, $depth);

        $xml = '  <url>' . "\n";
        $xml .= '    <loc>' . $this->escape($loc) . '</loc>' . "\n";
        $xml .= $this->alternates($document->slug);

        if ($lastmod !== null) {
            $xml .= '    <lastmod>' . $lastmod . '</lastmod>' . "\n";
        }

        $xml .= '    <priority>' . number_format($priority, 1, '.', '') . '</priority>' . "\n";
        $xml .= '  </url>' . "\n";

        return $xml;
    }

    /**
     * Emit an `xhtml:link rel="alternate"` for every available locale (plus an
     * `x-default` pointing at the unprefixed default) so crawlers discover and
     * correctly attribute each language. Empty when URL-path locales are off.
     */
    private function alternates(string $slug): string
    {
        if (! Locale::urlEnabled()) {
            return '';
        }

        $xml = '';

        foreach (array_keys(Locale::available()) as $code) {
            $xml .= $this->alternate($code, DocumentUrl::localized($slug, $code));
        }

        return $xml . $this->alternate('x-default', DocumentUrl::localized($slug, Locale::fallback()));
    }

    private function alternate(string $hreflang, string $href): string
    {
        return '    <xhtml:link rel="alternate" hreflang="' . $this->escape($hreflang)
            . '" href="' . $this->escape($href) . '"/>' . "\n";
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function lastmod(Document $document): ?string
    {
        $carbon = $document->metadata->updatedAtCarbon();

        if ($carbon !== null) {
            return $carbon->toAtomString();
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
        // The xhtml namespace is only declared when per-locale alternates are
        // actually emitted, so a single-locale sitemap stays free of clutter.
        $xhtml = Locale::urlEnabled() ? ' xmlns:xhtml="http://www.w3.org/1999/xhtml"' : '';

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . $xhtml . '>' . "\n";
        $xml .= implode('', $urls);
        $xml .= '</urlset>' . "\n";

        return $xml;
    }
}

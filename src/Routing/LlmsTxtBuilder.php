<?php

declare(strict_types=1);

namespace Laradocs\Routing;

use Laradocs\Documents\Document;
use Laradocs\Documents\DocumentTree;
use Laradocs\Documents\TreeNode;
use Laradocs\Seo\Excerpt;
use Laradocs\Support\Config;

/**
 * Renders an llmstxt.org-compliant `llms.txt` from a document tree.
 *
 * The file is a curated, machine-readable map of the documentation: an H1
 * naming the site, an optional blockquote describing it, then one H2 per
 * top-level navigation section whose bullets link every page beneath it.
 * Sections and pages are emitted in tree order, so the file reads in the same
 * order as the sidebar.
 *
 * Pages belonging to no section (the docs index itself, plus any page sitting
 * at the root of the docs path) are gathered into a single leading list so the
 * file never opens with orphaned bullets above their heading.
 *
 * Inclusion rules are shared with {@see SitemapBuilder} via
 * {@see IndexInclusion}, so the two artifacts always describe the same site.
 */
final class LlmsTxtBuilder
{
    /**
     * Heading for the leading list of pages that belong to no section.
     */
    private const ROOT_HEADING = 'Docs';

    public function build(DocumentTree $tree): string
    {
        $body = $this->header();

        foreach ($this->sections($tree) as $section) {
            // A section whose every page is hidden, redirected or version-gated
            // contributes no bullets, and a heading with nothing under it tells
            // a reader nothing. Drop it.
            if ($section['bullets'] === []) {
                continue;
            }

            $body .= "\n## " . $this->oneLine($section['heading']) . "\n\n";
            $body .= implode("\n", $section['bullets']) . "\n";
        }

        return $body;
    }

    /**
     * The required H1 plus the optional description blockquote. Public so
     * {@see LlmsFullTxtBuilder} can open its corpus with the same header
     * rather than reimplementing the site-name/description fallback chains.
     */
    public function header(): string
    {
        $header = '# ' . $this->siteName() . "\n";
        $description = $this->siteDescription();

        return $description === null ? $header : $header . "\n> " . $description . "\n";
    }

    /**
     * The leading root list followed by one entry per top-level section, in
     * navigation order.
     *
     * @return array<int, array{heading: string, bullets: array<int, string>}>
     */
    private function sections(DocumentTree $tree): array
    {
        $root = [];

        if ($tree->rootDocument !== null && IndexInclusion::allows($tree->rootDocument)) {
            $root[] = $this->bullet($tree->rootDocument);
        }

        $sections = [];

        foreach ($tree->navigation() as $node) {
            if ($node->isSection()) {
                $bullets = [];
                // Walking the section node itself (rather than its children)
                // puts its own _index.md first, so the overview link precedes
                // the pages it introduces.
                $this->collect([$node], $bullets);

                $sections[] = ['heading' => $node->title, 'bullets' => $bullets];

                continue;
            }

            // A top-level node with no children is a page, not a section, so it
            // gets a bullet in the root list rather than a heading of its own.
            if ($node->document !== null && IndexInclusion::allows($node->document)) {
                $root[] = $this->bullet($node->document);
            }
        }

        return [['heading' => self::ROOT_HEADING, 'bullets' => $root], ...$sections];
    }

    /**
     * @param  array<int, TreeNode>  $nodes
     * @param  array<int, string>  $bullets
     */
    private function collect(array $nodes, array &$bullets): void
    {
        foreach ($nodes as $node) {
            $document = $node->document;

            if ($document !== null && IndexInclusion::allows($document)) {
                $bullets[] = $this->bullet($document);
            }

            $this->collect($node->children, $bullets);
        }
    }

    /**
     * One entry: `- [Title](absolute url): description`. The URL comes from
     * {@see DocumentUrl::toSlug()}, the same locale- and version-aware helper
     * the sitemap uses, so the two can never disagree about a page's address.
     */
    private function bullet(Document $document): string
    {
        $bullet = '- [' . $this->label($document->title()) . '](' . DocumentUrl::toSlug($document->slug) . ')';
        $description = $this->description($document);

        return $description === null ? $bullet : $bullet . ': ' . $description;
    }

    /**
     * The front-matter `description`, else an excerpt lifted from the page body.
     * Null when neither yields prose, in which case the bullet is emitted as a
     * bare link: the format makes the description optional.
     */
    private function description(Document $document): ?string
    {
        $explicit = $document->metadata->description;

        if ($explicit !== null && trim($explicit) !== '') {
            return $this->oneLine($explicit);
        }

        $excerpt = Excerpt::fromMarkdown($document->markdown);

        return $excerpt === null ? null : $this->oneLine($excerpt);
    }

    /**
     * Square brackets would terminate a markdown link label early, so escape
     * them; a stray newline in a title would break the bullet, so flatten it.
     */
    private function label(string $title): string
    {
        return str_replace(['[', ']'], ['\[', '\]'], $this->oneLine($title));
    }

    /**
     * The H1. Mirrors `SeoFactory::siteName()`: the SEO site name, falling back
     * to the UI brand title. That method is private to the SEO factory, so the
     * chain is restated here rather than widening a class's public surface for
     * one caller.
     */
    private function siteName(): string
    {
        return $this->stringOrNull('laradocs.seo.site_name')
            ?? Config::string('laradocs.ui.brand.title', 'Documentation');
    }

    /**
     * The blockquote. Mirrors `SeoFactory::fallbackDescription()`. Null when
     * neither key resolves, in which case the blockquote is omitted rather than
     * emitted empty.
     */
    private function siteDescription(): ?string
    {
        $description = $this->stringOrNull('laradocs.seo.description')
            ?? $this->stringOrNull('laradocs.ui.brand.tagline');

        return $description === null ? null : $this->oneLine($description);
    }

    /**
     * Treat an empty config string as absent so it falls through to the next
     * link in the chain instead of emitting a blank line.
     */
    private function stringOrNull(string $key): ?string
    {
        $value = Config::nullableString($key);

        return $value === null || $value === '' ? null : $value;
    }

    /**
     * Collapse every run of whitespace, newlines included, to single spaces.
     * Each bullet and the blockquote must occupy exactly one line.
     */
    private function oneLine(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}

<?php

declare(strict_types=1);

namespace Laradocs\Console;

use Illuminate\Console\Command;
use Laradocs\Documents\Document;
use Laradocs\Documents\DocumentCollection;
use Laradocs\Documents\DocumentTree;
use Laradocs\Documents\TreeNode;
use Laradocs\Laradocs;
use Laradocs\Support\Config;

final class CheckCommand extends Command
{
    protected $signature = 'docs:check {--json : Output results as JSON}';

    protected $description = 'Validate internal links, detect redirect cycles, and surface orphaned pages';

    /** @var array<string, true> */
    private $slugIndex = [];

    public function handle(Laradocs $laradocs): int
    {
        $documents = $laradocs->all();

        $this->slugIndex = [];

        foreach ($documents as $document) {
            $this->slugIndex[$document->slug] = true;
        }

        $tree = DocumentTree::fromDocuments($documents);
        $links = $this->collectLinks($documents);

        $brokenLinks = array_values(array_filter(
            $links,
            function (array $link): bool {
                return ! isset($this->slugIndex[$link['slug']]);
            },
        ));

        $linkedSlugs = [];

        foreach ($links as $link) {
            $linkedSlugs[$link['slug']] = true;
        }

        $orphans = $this->findOrphans($documents, $tree, $linkedSlugs);
        $redirectCycles = $this->findRedirectCycles($documents);

        $total = count($brokenLinks) + count($orphans) + count($redirectCycles);

        if ($this->option('json')) {
            $this->line(json_encode([
                'broken_links' => $brokenLinks,
                'orphans' => $orphans,
                'redirect_cycles' => $redirectCycles,
                'summary' => [
                    'broken_links' => count($brokenLinks),
                    'orphans' => count($orphans),
                    'redirect_cycles' => count($redirectCycles),
                    'total' => $total,
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return $total > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->renderFindings($brokenLinks, $orphans, $redirectCycles);

        if ($total === 0) {
            $this->info('All checks passed.');
        }

        return $total > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Extract every internal markdown link (those pointing at the docs route
     * prefix) across all documents, paired with their resolved target slug.
     *
     * @return array<int, array{source: string, href: string, slug: string}>
     */
    private function collectLinks(DocumentCollection $documents): array
    {
        $prefix = '/' . trim(Config::string('laradocs.route.prefix', 'docs'), '/');
        $links = [];

        foreach ($documents as $document) {
            preg_match_all('/\[[^\]]*\]\(([^)\s]+)\)/', $document->markdown, $matches);

            foreach ($matches[1] as $href) {
                if (strncmp($href, $prefix, strlen($prefix)) !== 0) {
                    continue;
                }

                $links[] = [
                    'source' => $document->slug,
                    'href' => $href,
                    'slug' => $this->hrefToSlug($href, $prefix),
                ];
            }
        }

        return $links;
    }

    /**
     * Report documents that are unreachable: absent from the navigation tree
     * (i.e. hidden) and not the target of any internal link from another page.
     *
     * Visible documents always appear in the auto-generated navigation, so the
     * only orphans are hidden pages that nothing links to — dead content that
     * can be reached by neither the menu nor a cross-reference.
     *
     * @param  array<string, true>  $linkedSlugs
     * @return array<int, array{slug: string, title: string, path: string}>
     */
    private function findOrphans(DocumentCollection $documents, DocumentTree $tree, array $linkedSlugs): array
    {
        $navSlugs = [];

        if ($tree->rootDocument !== null) {
            $navSlugs[$tree->rootDocument->slug] = true;
        }

        $this->collectNavSlugs($tree->navigation(), $navSlugs);

        $findings = [];

        foreach ($documents as $document) {
            if (isset($navSlugs[$document->slug]) || isset($linkedSlugs[$document->slug])) {
                continue;
            }

            $findings[] = [
                'slug' => $document->slug,
                'title' => $document->title(),
                'path' => $document->relativePath,
            ];
        }

        return $findings;
    }

    /**
     * Walk every document's redirect chain and report any that form a cycle.
     *
     * @return array<int, array{cycle: array<int, string>}>
     */
    private function findRedirectCycles(DocumentCollection $documents): array
    {
        $redirectMap = $this->buildRedirectMap($documents);

        $findings = [];
        $checked = [];

        foreach (array_keys($redirectMap) as $start) {
            if (isset($checked[$start])) {
                continue;
            }

            $chain = [];
            $position = [];
            $current = $start;

            while (isset($redirectMap[$current]) && ! isset($position[$current])) {
                $position[$current] = count($chain);
                $chain[] = $current;
                $checked[$current] = true;
                $current = $redirectMap[$current];
            }

            if (isset($position[$current])) {
                $cycle = array_slice($chain, $position[$current]);
                $cycle[] = $current;
                $findings[] = ['cycle' => $cycle];
            }
        }

        return $findings;
    }

    /**
     * Build a slug → target map of every redirect whose destination is a known document.
     *
     * @return array<string, string>
     */
    private function buildRedirectMap(DocumentCollection $documents): array
    {
        $prefix = '/' . trim(Config::string('laradocs.route.prefix', 'docs'), '/');
        $map = [];

        foreach ($documents as $document) {
            $raw = $document->redirect();

            if ($raw === null || $raw === '') {
                continue;
            }

            $target = strncmp($raw, $prefix, strlen($prefix)) === 0
                ? $this->hrefToSlug($raw, $prefix)
                : $raw;

            if (isset($this->slugIndex[$target])) {
                $map[$document->slug] = $target;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, TreeNode>  $nodes
     * @param  array<string, true>  $slugs
     */
    private function collectNavSlugs(array $nodes, array &$slugs): void
    {
        foreach ($nodes as $node) {
            if ($node->document !== null) {
                $slugs[$node->document->slug] = true;
            }
            $this->collectNavSlugs($node->children, $slugs);
        }
    }

    private function hrefToSlug(string $href, string $prefix): string
    {
        [$path] = explode('#', $href, 2);

        return ltrim((string) substr($path, strlen($prefix)), '/');
    }

    /**
     * @param  array<int, array{source: string, href: string, slug: string}>  $brokenLinks
     * @param  array<int, array{slug: string, title: string, path: string}>  $orphans
     * @param  array<int, array{cycle: array<int, string>}>  $redirectCycles
     */
    private function renderFindings(array $brokenLinks, array $orphans, array $redirectCycles): void
    {
        foreach ($brokenLinks as $finding) {
            $this->twoColumnDetail(
                '<fg=red>BROKEN LINK</>',
                sprintf('%s → <href=%s>%s</>', $finding['source'], $finding['href'], $finding['href']),
            );
        }

        foreach ($orphans as $orphan) {
            $this->twoColumnDetail(
                '<fg=yellow>ORPHAN</>',
                sprintf('%s  <fg=gray>(%s)</>', $orphan['slug'], $orphan['path']),
            );
        }

        foreach ($redirectCycles as $cycle) {
            $this->twoColumnDetail(
                '<fg=red>REDIRECT CYCLE</>',
                implode(' → ', $cycle['cycle']),
            );
        }
    }

    private function twoColumnDetail(string $label, string $detail): void
    {
        $this->line($label . '  ' . $detail);
    }
}

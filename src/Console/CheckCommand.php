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
    private array $slugIndex = [];

    public function handle(Laradocs $laradocs): int
    {
        $documents = $laradocs->all();

        $this->slugIndex = $documents
            ->mapWithKeys(fn (Document $doc): array => [$doc->slug => true])
            ->all();

        $tree = DocumentTree::fromDocuments($documents);

        $brokenLinks = $this->findBrokenLinks($documents);
        $orphans = $this->findOrphans($documents, $tree);
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
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $total > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->renderFindings($brokenLinks, $orphans, $redirectCycles);

        if ($total === 0) {
            $this->components->info('All checks passed.');
        }

        return $total > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Parse every document's markdown for internal links and report any whose
     * resolved slug does not match a loaded document.
     *
     * @param  DocumentCollection<int, Document>  $documents
     * @return array<int, array{source: string, href: string, slug: string}>
     */
    private function findBrokenLinks(DocumentCollection $documents): array
    {
        $prefix = '/' . trim(Config::string('laradocs.route.prefix', 'docs'), '/');
        $findings = [];

        foreach ($documents as $document) {
            preg_match_all('/\[(?:[^\]]*)\]\(([^)\s]+)\)/', $document->markdown, $matches);

            foreach ($matches[1] as $href) {
                if (! str_starts_with($href, $prefix)) {
                    continue;
                }

                $slug = $this->hrefToSlug($href, $prefix);

                if (! isset($this->slugIndex[$slug])) {
                    $findings[] = [
                        'source' => $document->slug,
                        'href' => $href,
                        'slug' => $slug,
                    ];
                }
            }
        }

        return $findings;
    }

    /**
     * Collect every slug reachable via the navigation tree and report visible
     * documents whose slug does not appear in it.
     *
     * @param  DocumentCollection<int, Document>  $documents
     * @return array<int, array{slug: string, title: string, path: string}>
     */
    private function findOrphans(DocumentCollection $documents, DocumentTree $tree): array
    {
        $navSlugs = [];

        if ($tree->rootDocument !== null) {
            $navSlugs[$tree->rootDocument->slug] = true;
        }

        $this->collectNavSlugs($tree->navigation(), $navSlugs);

        $findings = [];

        foreach ($documents as $document) {
            if ($document->isHidden()) {
                continue;
            }

            if (! isset($navSlugs[$document->slug])) {
                $findings[] = [
                    'slug' => $document->slug,
                    'title' => $document->title(),
                    'path' => $document->relativePath,
                ];
            }
        }

        return $findings;
    }

    /**
     * Walk every document's redirect chain and report any that form a cycle.
     *
     * @param  DocumentCollection<int, Document>  $documents
     * @return array<int, array{cycle: array<int, string>}>
     */
    private function findRedirectCycles(DocumentCollection $documents): array
    {
        $prefix = '/' . trim(Config::string('laradocs.route.prefix', 'docs'), '/');

        /** @var array<string, string> $redirectMap */
        $redirectMap = [];

        foreach ($documents as $document) {
            $raw = $document->redirect();

            if ($raw === null || $raw === '') {
                continue;
            }

            $target = str_starts_with($raw, $prefix)
                ? $this->hrefToSlug($raw, $prefix)
                : $raw;

            if (isset($this->slugIndex[$target])) {
                $redirectMap[$document->slug] = $target;
            }
        }

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

        return ltrim(substr($path, strlen($prefix)), '/');
    }

    /**
     * @param  array<int, array{source: string, href: string, slug: string}>  $brokenLinks
     * @param  array<int, array{slug: string, title: string, path: string}>  $orphans
     * @param  array<int, array{cycle: array<int, string>}>  $redirectCycles
     */
    private function renderFindings(array $brokenLinks, array $orphans, array $redirectCycles): void
    {
        foreach ($brokenLinks as $finding) {
            $this->components->twoColumnDetail(
                '<fg=red>BROKEN LINK</>',
                sprintf('%s → <href=%s>%s</>', $finding['source'], $finding['href'], $finding['href']),
            );
        }

        foreach ($orphans as $orphan) {
            $this->components->twoColumnDetail(
                '<fg=yellow>ORPHAN</>',
                sprintf('%s  <fg=gray>(%s)</>', $orphan['slug'], $orphan['path']),
            );
        }

        foreach ($redirectCycles as $cycle) {
            $this->components->twoColumnDetail(
                '<fg=red>REDIRECT CYCLE</>',
                implode(' → ', $cycle['cycle']),
            );
        }
    }
}

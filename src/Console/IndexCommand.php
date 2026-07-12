<?php

declare(strict_types=1);

namespace Laradocs\Console;

use Illuminate\Console\Command;
use Laradocs\Laradocs;
use Laradocs\Search\Contracts\SearchEngine;
use Laradocs\Support\Config;
use Laradocs\Support\VersionInfo;
use Laradocs\Support\VersionRegistry;
use Throwable;

final class IndexCommand extends Command
{
    // The option is `--docs-version`, not `--version`: Symfony Console reserves the
    // global `-V/--version` flag, which both collides on registration and is
    // short-circuited by Application::doRun (it prints the framework version and
    // exits before the command runs). `--docs-version` is the conflict-free name.
    protected $signature = 'laradocs:index {--docs-version= : Rebuild the index for a single version key only}';

    protected $description = 'Build the full-text search index and push it to the configured engine';

    public function handle(Laradocs $laradocs, SearchEngine $engine, VersionRegistry $versions): int
    {
        $available = $versions->all();
        $requested = $this->option('docs-version');

        if (is_string($requested) && $requested !== '') {
            return $this->rebuildSingle($laradocs, $engine, $available, $requested);
        }

        if (! Config::bool('laradocs.versions.enabled', false) || $available === []) {
            return $this->rebuild($laradocs, $engine, null);
        }

        return $this->rebuildAll($laradocs, $engine, $available);
    }

    /**
     * Validate a specific version handle and rebuild only that version's index.
     *
     * @param  array<string, VersionInfo>  $available
     */
    private function rebuildSingle(Laradocs $laradocs, SearchEngine $engine, array $available, string $requested): int
    {
        if (! isset($available[$requested])) {
            $this->error(sprintf('Unknown version "%s".', $requested));

            return self::FAILURE;
        }

        return $this->rebuild($laradocs, $engine, $requested);
    }

    /**
     * Rebuild every detected version in sequence, returning FAILURE if any sync
     * fails so CI catches partial index breakage.
     *
     * @param  array<string, VersionInfo>  $available
     */
    private function rebuildAll(Laradocs $laradocs, SearchEngine $engine, array $available): int
    {
        $failed = false;

        foreach (array_keys($available) as $key) {
            $key = (string) $key;

            $this->info(sprintf('Rebuilding the search index for version %s.', $key));

            if ($this->rebuild($laradocs, $engine, $key) !== self::SUCCESS) {
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Rebuild and push the index for a single version, restoring the previous
     * runtime version afterwards. A null $version rebuilds in place.
     */
    private function rebuild(Laradocs $laradocs, SearchEngine $engine, ?string $version): int
    {
        if ($version === null) {
            return $this->sync($laradocs, $engine);
        }

        $previous = Config::nullableString('laradocs._current_version');

        config(['laradocs._current_version' => $version]);

        try {
            return $this->sync($laradocs, $engine);
        } finally {
            config(['laradocs._current_version' => $previous]);
        }
    }

    /**
     * Build the search index for the active version and push it to the engine.
     */
    private function sync(Laradocs $laradocs, SearchEngine $engine): int
    {
        $index = $laradocs->searchIndex();

        try {
            $engine->sync($index);
        } catch (Throwable $e) {
            $this->error(sprintf(
                'Failed to index %d page(s) for search (%s engine).',
                count($index),
                $engine->name(),
            ));
            $this->error('  ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Indexed %d page(s) for search (%s engine).',
            count($index),
            $engine->name(),
        ));

        return self::SUCCESS;
    }
}

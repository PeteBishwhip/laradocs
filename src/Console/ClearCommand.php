<?php

declare(strict_types=1);

namespace Laradocs\Console;

use Illuminate\Console\Command;
use Laradocs\Cache\DocumentCache;
use Laradocs\Search\Contracts\SearchEngine;
use Throwable;

final class ClearCommand extends Command
{
    protected $signature = 'laradocs:clear';

    protected $description = 'Clear all cached documentation HTML, navigation data and the search index';

    public function handle(DocumentCache $cache, SearchEngine $engine): int
    {
        $cache->flush();

        try {
            $engine->flush();
        } catch (Throwable $e) {
            $this->error(sprintf('Failed to flush the search index (%s engine).', $engine->name()));
            $this->error('  ' . $e->getMessage());
        }

        $this->info('Documentation cache cleared.');

        return self::SUCCESS;
    }
}

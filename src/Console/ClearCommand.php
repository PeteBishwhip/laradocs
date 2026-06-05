<?php

declare(strict_types=1);

namespace Laradocs\Console;

use Illuminate\Console\Command;
use Laradocs\Cache\DocumentCache;
use Laradocs\Search\Contracts\SearchEngine;

final class ClearCommand extends Command
{
    protected $signature = 'laradocs:clear';

    protected $description = 'Clear all cached documentation HTML, navigation data and the search index';

    public function handle(DocumentCache $cache, SearchEngine $engine): int
    {
        $cache->flush();

        $engine->flush();

        $this->components->info('Documentation cache cleared.');

        return self::SUCCESS;
    }
}

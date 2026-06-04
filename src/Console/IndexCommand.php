<?php

declare(strict_types=1);

namespace Laradocs\Console;

use Illuminate\Console\Command;
use Laradocs\Laradocs;
use Laradocs\Search\Contracts\SearchEngine;

final class IndexCommand extends Command
{
    protected $signature = 'laradocs:index';

    protected $description = 'Build the full-text search index and push it to the configured engine';

    public function handle(Laradocs $laradocs, SearchEngine $engine): int
    {
        $index = $laradocs->searchIndex();

        $engine->sync($index);

        $this->components->info(sprintf(
            'Indexed %d page(s) for search (%s engine).',
            count($index),
            $engine->name(),
        ));

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace Laradocs\Console;

use Illuminate\Console\Command;
use Laradocs\Laradocs;
use Laradocs\Search\Contracts\SearchEngine;
use Throwable;

final class IndexCommand extends Command
{
    protected $signature = 'laradocs:index';

    protected $description = 'Build the full-text search index and push it to the configured engine';

    public function handle(Laradocs $laradocs, SearchEngine $engine): int
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

        $this->components->info(sprintf(
            'Indexed %d page(s) for search (%s engine).',
            count($index),
            $engine->name(),
        ));

        return self::SUCCESS;
    }
}

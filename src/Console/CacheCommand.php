<?php

declare(strict_types=1);

namespace Laradocs\Console;

use Illuminate\Console\Command;
use Laradocs\Documents\Document;
use Laradocs\Laradocs;

final class CacheCommand extends Command
{
    protected $signature = 'laradocs:cache';

    protected $description = 'Pre-render and cache all documentation pages and the navigation tree';

    public function handle(Laradocs $laradocs): int
    {
        $documents = $laradocs->all();

        $laradocs->tree();
        $laradocs->sitemap();

        $documents->each(fn (Document $document) => $laradocs->render($document));

        $this->call('laradocs:index');

        $this->components->info(sprintf('Cached %d documentation page(s).', $documents->count()));

        return self::SUCCESS;
    }
}

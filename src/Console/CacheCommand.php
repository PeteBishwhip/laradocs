<?php

declare(strict_types=1);

namespace Laradocs\Console;

use Illuminate\Console\Command;
use Laradocs\Documents\Document;
use Laradocs\Documents\DocumentCollection;
use Laradocs\Laradocs;

final class CacheCommand extends Command
{
    protected $signature = 'laradocs:cache';

    protected $description = 'Pre-render and cache all documentation pages and the navigation tree';

    public function handle(Laradocs $laradocs): int
    {
        // Warming happens for every document, including any a visibility rule
        // hides: rendered HTML is cached per file and is still only served to a
        // reader allowed to see that file.
        $documents = $laradocs->withoutVisibility(function () use ($laradocs): DocumentCollection {
            $documents = $laradocs->all();

            $laradocs->tree();
            $laradocs->sitemap();
            $laradocs->llmsTxt();
            $laradocs->llmsFullTxt();

            $documents->each(fn (Document $document) => $laradocs->render($document));

            return $documents;
        });

        // Deliberately outside: a search index is one artifact shared by every
        // reader, so it is built from what the rule allows and nothing more.
        $this->call('laradocs:index');

        $this->components->info(sprintf('Cached %d documentation page(s).', $documents->count()));

        return self::SUCCESS;
    }
}

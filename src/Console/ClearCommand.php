<?php

declare(strict_types=1);

namespace Laradocs\Console;

use Illuminate\Console\Command;
use Laradocs\Cache\DocumentCache;

final class ClearCommand extends Command
{
    protected $signature = 'laradocs:clear';

    protected $description = 'Clear all cached documentation HTML and navigation data';

    public function handle(DocumentCache $cache): int
    {
        $cache->flush();

        $this->components->info('Documentation cache cleared.');

        return self::SUCCESS;
    }
}

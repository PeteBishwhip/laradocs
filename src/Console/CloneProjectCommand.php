<?php

declare(strict_types=1);

namespace Laradocs\Console;

use Illuminate\Console\Command;
use Laradocs\Console\Concerns\ResolvesDeploySite;
use Laradocs\Deploy\ApiClient;
use Laradocs\Deploy\LocalDocs;

final class CloneProjectCommand extends Command
{
    use ResolvesDeploySite;

    protected $signature = 'laradocs:clone-project
        {--site= : The site slug (defaults to LARADOCS_SITE)}
        {--force : Overwrite the local docs directory if it already has files}';

    protected $description = 'Pull a hosted site\'s documentation files into the local docs directory';

    public function handle(ApiClient $api, LocalDocs $docs): int
    {
        $slug = $this->resolveSite();

        if ($slug === null) {
            $this->error('No site specified. Pass --site or set LARADOCS_SITE.');

            return self::FAILURE;
        }

        return $this->guardApi(function () use ($api, $docs, $slug): int {
            return $this->pull($api, $docs, $slug);
        });
    }

    private function pull(ApiClient $api, LocalDocs $docs, string $slug): int
    {
        if (! $docs->isEmpty() && ! $this->option('force')) {
            $this->error("{$docs->path()} already contains docs. Re-run with --force to overwrite.");

            return self::FAILURE;
        }

        $files = $api->files($slug);

        if ($files === []) {
            $this->warn("{$slug} has no files to pull yet.");

            return self::SUCCESS;
        }

        $written = $docs->write($files);

        $this->info('Pulled ' . count($written) . " file(s) into {$docs->path()}.");

        return self::SUCCESS;
    }
}

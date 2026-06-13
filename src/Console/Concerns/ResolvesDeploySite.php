<?php

declare(strict_types=1);

namespace Laradocs\Console\Concerns;

use Illuminate\Console\Command;
use Laradocs\Deploy\ApiException;
use Laradocs\Deploy\DeployException;
use Laradocs\Deploy\NotAuthenticatedException;
use Laradocs\Support\Config;

trait ResolvesDeploySite
{
    /**
     * Resolve the target site slug from the --site option or the configured
     * LARADOCS_SITE default.
     */
    protected function resolveSite(): ?string
    {
        $slug = (string) ($this->option('site') ?: Config::string('laradocs.deploy.site'));

        return $slug !== '' ? $slug : null;
    }

    /**
     * Run the given work, translating deploy-flow exceptions into a clear
     * error and a failure exit code.
     *
     * @param  callable(): int  $work
     */
    protected function guardApi(callable $work): int
    {
        try {
            return $work();
        } catch (ApiException $e) {
            $this->components->error($e->userMessage());

            return Command::FAILURE;
        } catch (DeployException|NotAuthenticatedException $e) {
            $this->components->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}

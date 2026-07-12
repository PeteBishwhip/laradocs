<?php

declare(strict_types=1);

namespace Laradocs\Console;

use Illuminate\Console\Command;
use Laradocs\Console\Concerns\ResolvesDeploySite;
use Laradocs\Deploy\ApiClient;
use Laradocs\Deploy\DeployException;
use Laradocs\Deploy\Json;
use Laradocs\Deploy\LocalDocs;

final class DeployCommand extends Command
{
    use ResolvesDeploySite;

    protected $signature = 'laradocs:deploy
        {--site= : The site slug (defaults to LARADOCS_SITE)}
        {--ref= : Deploy a specific git ref (branch, tag, or SHA) — GitHub sites only}
        {--tag= : Shorthand for --ref with a tag}
        {--sha= : Shorthand for --ref with a commit SHA}
        {--branch= : Shorthand for --ref with a branch}
        {--git : Force git-source semantics (no-op for flat sites)}';

    protected $description = 'Deploy your documentation to a hosted Laradocs site';

    public function handle(ApiClient $api, LocalDocs $docs): int
    {
        $slug = $this->resolveSite();

        if ($slug === null) {
            $this->error('No site specified. Pass --site or set LARADOCS_SITE.');

            return self::FAILURE;
        }

        return $this->guardApi(function () use ($api, $docs, $slug): int {
            return $this->deploy($api, $docs, $slug, $this->refOption());
        });
    }

    private function deploy(ApiClient $api, LocalDocs $docs, string $slug, string $ref): int
    {
        $site = $api->getSite($slug);
        $source = Json::string($site['source'] ?? null, 'github');

        $payload = $source === 'flat' ? $this->flatPayload($docs) : $this->gitPayload($ref);

        return $this->report($slug, $api->deploy($slug, $payload));
    }

    /**
     * The git ref to deploy, taken from the first of the --tag/--sha/--branch/--ref
     * options that is set.
     */
    private function refOption(): string
    {
        foreach (['tag', 'sha', 'branch', 'ref'] as $option) {
            $value = $this->option($option);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    private function flatPayload(LocalDocs $docs): array
    {
        $files = $docs->read();

        if ($files === []) {
            throw new DeployException("No markdown files found in {$docs->path()}. Aborting so the site is not wiped.");
        }

        $this->info('Uploading ' . count($files) . ' file(s) …');

        return ['files' => $files];
    }

    /**
     * @return array<string, mixed>
     */
    private function gitPayload(string $ref): array
    {
        $this->info($ref !== ''
            ? "Deploying from ref {$ref} …"
            : 'Deploying from the connected branch …');

        return $ref !== '' ? ['ref' => $ref] : [];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function report(string $slug, array $result): int
    {
        /** @var array<string, mixed>|null $deployment */
        $deployment = $result['deployment'] ?? null;

        if (is_array($deployment)) {
            $this->info(sprintf(
                'Deployed %s: %d written, %d pruned.',
                $slug,
                Json::int($deployment['files_written'] ?? null),
                Json::int($deployment['files_pruned'] ?? null),
            ));
        } else {
            $this->info("Deploy queued for {$slug}.");
        }

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace Laradocs\Console;

use Illuminate\Console\Command;
use Laradocs\Support\VersionInfo;
use Laradocs\Support\VersionRegistry;

final class VersionsCommand extends Command
{
    protected $signature = 'laradocs:versions';

    protected $description = 'List the detected documentation versions and their metadata';

    public function handle(VersionRegistry $versions): int
    {
        $all = $versions->all();

        if ($all === []) {
            $this->components->info('No documentation versions detected. Enable versioning and add version directories or `versions.available` entries.');

            return self::SUCCESS;
        }

        $latest = $versions->latest();

        $this->table(
            ['key', 'label', 'semver', 'stable', 'deprecated', 'hidden', 'latest'],
            array_map(
                fn (VersionInfo $info): array => [
                    $info->key,
                    $info->label,
                    $info->semver ?? '',
                    $this->yesNo($info->stable),
                    $this->yesNo($info->deprecated),
                    $this->yesNo($info->hidden),
                    $this->yesNo($info->key === $latest),
                ],
                array_values($all),
            ),
        );

        return self::SUCCESS;
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }
}

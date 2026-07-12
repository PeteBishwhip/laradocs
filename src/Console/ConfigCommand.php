<?php

declare(strict_types=1);

namespace Laradocs\Console;

use Illuminate\Console\Command;
use Laradocs\Console\Concerns\ResolvesDeploySite;
use Laradocs\Deploy\ApiClient;
use Laradocs\Deploy\Json;
use Laradocs\Support\Config;

final class ConfigCommand extends Command
{
    use ResolvesDeploySite;

    protected $signature = 'laradocs:config
        {key? : The config key to read or write (accent, title, logo, preset)}
        {value? : The value to set; omit to read}
        {--site= : The site slug (defaults to LARADOCS_SITE)}
        {--sync : Compare local ui config with the remote site and optionally push}';

    protected $description = 'Read, update, or sync a hosted site\'s configuration';

    public function handle(ApiClient $api): int
    {
        $slug = $this->resolveSite();

        if ($slug === null) {
            $this->error('No site specified. Pass --site or set LARADOCS_SITE.');

            return self::FAILURE;
        }

        return $this->guardApi(function () use ($api, $slug): int {
            return $this->option('sync')
                ? $this->sync($api, $slug)
                : $this->readOrWrite($api, $slug);
        });
    }

    private function readOrWrite(ApiClient $api, string $slug): int
    {
        $key = $this->argument('key');
        $value = $this->argument('value');

        if ($key === null) {
            $config = $this->remoteConfig($api, $slug);

            $this->table(
                ['Key', 'Value'],
                collect($config)->map(function ($v, $k): array {
                    return [$k, (string) $v];
                })->values()->all(),
            );

            return self::SUCCESS;
        }

        if ($value === null) {
            $config = $this->remoteConfig($api, $slug);
            $this->line((string) ($config[$key] ?? ''));

            return self::SUCCESS;
        }

        $api->updateConfig($slug, [$key => $value]);
        $this->info("Updated {$key} on {$slug}.");

        return self::SUCCESS;
    }

    private function sync(ApiClient $api, string $slug): int
    {
        $remote = $this->remoteConfig($api, $slug);
        $local = $this->localConfig();

        $differences = [];
        foreach ($local as $key => $localValue) {
            $remoteValue = (string) ($remote[$key] ?? '');

            if ($localValue !== $remoteValue) {
                $differences[$key] = $localValue;
                $this->line("  {$key}: <fg=red>{$remoteValue}</> → <fg=green>{$localValue}</>");
            }
        }

        if ($differences === []) {
            $this->info('Local config already matches the remote site.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Push these local values to the remote site?', true)) {
            return self::SUCCESS;
        }

        $api->updateConfig($slug, $differences);
        $this->info('Pushed ' . count($differences) . ' value(s) to ' . $slug . '.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function remoteConfig(ApiClient $api, string $slug): array
    {
        return array_map(
            static function ($value): string {
                return Json::string($value);
            },
            Json::object($api->getConfig($slug)['config'] ?? []),
        );
    }

    /**
     * Map the local package's ui.* settings onto the remote config keys,
     * skipping anything that isn't set locally.
     *
     * @return array<string, string>
     */
    private function localConfig(): array
    {
        $candidates = [
            'accent' => Config::nullableString('laradocs.ui.accent'),
            'title' => Config::nullableString('laradocs.ui.brand.title'),
            'logo' => Config::nullableString('laradocs.ui.brand.logo'),
            'preset' => Config::nullableString('laradocs.ui.preset'),
        ];

        return array_filter(
            $candidates,
            static function (?string $value): bool {
                return $value !== null && $value !== '';
            },
        );
    }
}

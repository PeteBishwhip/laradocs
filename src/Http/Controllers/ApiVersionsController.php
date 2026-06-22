<?php

declare(strict_types=1);

namespace Laradocs\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Laradocs\Support\Version;
use Laradocs\Support\VersionInfo;
use Laradocs\Support\VersionRegistry;

/**
 * Exposes the full version list as JSON so frontend or external consumers can
 * build dynamic, version-aware navigation without scraping the rendered page.
 *
 * Hidden versions are omitted — they remain reachable by their handle but are
 * never advertised, mirroring how the selector view treats them.
 */
final class ApiVersionsController
{
    public function __construct(
        private readonly VersionRegistry $registry,
    ) {}

    public function __invoke(): JsonResponse
    {
        $default = Version::default();

        $versions = [];

        foreach ($this->registry->all() as $key => $info) {
            if ($info->hidden) {
                continue;
            }

            $versions[] = $this->serialize((string) $key, $info, $default);
        }

        return new JsonResponse([
            'versions' => $versions,
            'default' => $default,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(string $key, VersionInfo $info, ?string $default): array
    {
        return [
            'key' => $key,
            'label' => $info->label,
            'semver' => $info->semver,
            'stable' => $info->stable,
            'deprecated' => $info->deprecated,
            'preRelease' => $info->preRelease,
            'latest' => $info->latest,
            'default' => $key === $default,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Laradocs\Icons;

use Illuminate\Filesystem\Filesystem;

/**
 * Reads Heroicons SVG files from a local installation of the heroicons npm
 * package (https://github.com/tailwindlabs/heroicons).
 *
 * Supports all four variants shipped with heroicons v2:
 *   outline — 24px stroke icons (default)
 *   solid   — 24px filled icons
 *   mini    — 20px filled icons
 *   micro   — 16px filled icons
 */
final class HeroiconProvider
{
    public function __construct(
        private readonly string $basePath,
        private readonly Filesystem $files,
    ) {}

    public function __invoke(string $name, string $variant = 'outline'): string
    {
        if (! $this->validName($name)) {
            return '';
        }

        $variant = in_array($variant, ['outline', 'solid', 'mini', 'micro'], true)
            ? $variant
            : 'outline';

        $size = match ($variant) {
            'mini' => '20',
            'micro' => '16',
            default => '24',
        };

        $path = rtrim($this->basePath, '/') . "/{$size}/{$variant}/{$name}.svg";

        if (! $this->files->exists($path)) {
            return '';
        }

        $svg = $this->files->get($path);

        return trim((string) preg_replace('/<\?xml[^>]*\?>\s*/', '', $svg));
    }

    private function validName(string $name): bool
    {
        return preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/i', $name) === 1;
    }

    /**
     * Attempt to locate the heroicons npm package under common paths relative
     * to the application root. Returns null when the package is not installed.
     */
    public static function detect(): ?string
    {
        foreach ([
            base_path('node_modules/heroicons'),
            base_path('vendor/heroicons'),
        ] as $path) {
            if (is_dir($path)) {
                return $path;
            }
        }

        return null;
    }
}

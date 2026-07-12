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
    /**
     * @readonly
     * @var string
     */
    private $basePath;
    /**
     * @readonly
     * @var \Illuminate\Filesystem\Filesystem
     */
    private $files;
    public function __construct(string $basePath, Filesystem $files)
    {
        $this->basePath = $basePath;
        $this->files = $files;
    }

    public function __invoke(string $name, string $variant = 'outline'): string
    {
        $variant = in_array($variant, ['outline', 'solid', 'mini', 'micro'], true)
            ? $variant
            : 'outline';

        switch ($variant) {
            case 'mini':
                $size = '20';
                break;
            case 'micro':
                $size = '16';
                break;
            default:
                $size = '24';
                break;
        }

        $path = rtrim($this->basePath, '/') . "/{$size}/{$variant}/{$name}.svg";

        if (! $this->files->exists($path)) {
            return '';
        }

        $svg = $this->files->get($path);

        return trim((string) preg_replace('/<\?xml[^>]*\?>\s*/', '', $svg));
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

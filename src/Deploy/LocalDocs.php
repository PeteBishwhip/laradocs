<?php

declare(strict_types=1);

namespace Laradocs\Deploy;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Laradocs\Support\Config;

/**
 * Reads and writes the consuming app's local docs directory as a
 * relativePath => contents map, the shape the deploy API speaks.
 */
final class LocalDocs
{
    /**
     * @var \Illuminate\Filesystem\Filesystem
     */
    private $files;
    public function __construct(Filesystem $files)
    {
        $this->files = $files;
    }

    public function path(): string
    {
        return Config::string('laradocs.docs.path', base_path('docs'));
    }

    /**
     * @return array<string, string>
     */
    public function read(): array
    {
        $root = $this->path();

        if (! $this->files->isDirectory($root)) {
            return [];
        }

        $extensions = $this->extensions();
        $contents = [];

        foreach ($this->files->allFiles($root) as $file) {
            if (! in_array(strtolower($file->getExtension()), $extensions, true)) {
                continue;
            }

            $relative = ltrim(Str::after($file->getPathname(), $root), '/\\');
            $relative = str_replace('\\', '/', $relative);

            $contents[$relative] = (string) $this->files->get($file->getPathname());
        }

        ksort($contents);

        return $contents;
    }

    /**
     * Write a path => contents map into the docs directory, returning the
     * relative paths written.
     *
     * @param  array<string, string>  $files
     * @return array<int, string>
     */
    public function write(array $files): array
    {
        $root = rtrim($this->path(), '/\\');
        $written = [];

        foreach ($files as $relative => $body) {
            $relative = str_replace('\\', '/', trim((string) $relative));

            if ($relative === '' || strpos($relative, '..') !== false) {
                continue;
            }

            $target = $root . '/' . $relative;
            $this->files->ensureDirectoryExists($this->files->dirname($target));
            $this->files->put($target, (string) $body);
            $written[] = $relative;
        }

        return $written;
    }

    public function isEmpty(): bool
    {
        return $this->read() === [];
    }

    /**
     * @return array<int, string>
     */
    private function extensions(): array
    {
        $extensions = Config::array('laradocs.docs.extensions', ['md', 'markdown']);

        return array_values(array_map(
            static function ($ext): string {
                return strtolower(ltrim(Json::string($ext), '.'));
            },
            $extensions,
        ));
    }
}

<?php

declare(strict_types=1);

namespace Laradocs\Loaders;

use Closure;
use Illuminate\Filesystem\Filesystem;
use Laradocs\Contracts\DocumentLoader;
use Laradocs\Contracts\MetadataResolver;
use Laradocs\Documents\Document;
use Laradocs\Documents\DocumentCollection;
use Laradocs\Metadata\Metadata;
use Laradocs\Routing\SlugResolver;
use SplFileInfo;

final class FilesystemLoader implements DocumentLoader
{
    /**
     * @param  string|Closure(): string  $path  Eagerly resolved string, or a closure
     *                                          re-invoked at each call so consumer apps
     *                                          can retarget the docs path per request.
     * @param  array<int, string>  $extensions
     * @param  array<int, string>  $ignoredPatterns
     * @param  array<string, mixed>  $metadataDefaults
     */
    public function __construct(
        private readonly Filesystem $files,
        private readonly MetadataResolver $metadataResolver,
        private readonly SlugResolver $slugResolver,
        private readonly string|Closure $path,
        private readonly array $extensions = ['md'],
        private readonly array $ignoredPatterns = [],
        private readonly array $metadataDefaults = [],
    ) {}

    public function all(): DocumentCollection
    {
        $path = $this->path();

        if (! $this->files->isDirectory($path)) {
            return new DocumentCollection;
        }

        $documents = new DocumentCollection;

        foreach ($this->files->allFiles($path) as $file) {
            if (! $this->shouldInclude($file, $path)) {
                continue;
            }

            $documents->push($this->makeDocument($file, $path));
        }

        return $documents;
    }

    public function find(string $slug): ?Document
    {
        return $this->all()->findBySlug($slug);
    }

    /**
     * Resolve the docs path lazily so a closure-backed path picks up runtime
     * config changes (`laradocs.docs.path`) without reconstructing the loader.
     */
    private function path(): string
    {
        return is_string($this->path) ? $this->path : ($this->path)();
    }

    private function shouldInclude(SplFileInfo $file, string $basePath): bool
    {
        if (! in_array(strtolower($file->getExtension()), $this->extensions, true)) {
            return false;
        }

        $relative = $this->relativePath($file, $basePath);

        foreach (explode('/', $relative) as $segment) {
            foreach ($this->ignoredPatterns as $pattern) {
                if (fnmatch($pattern, $segment)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function makeDocument(SplFileInfo $file, string $basePath): Document
    {
        $relative = $this->relativePath($file, $basePath);

        [$matter, $body] = $this->metadataResolver->resolve($this->files->get($file->getPathname()));

        $metadata = Metadata::fromArray($matter, $this->metadataDefaults);

        $slug = $this->slugResolver->resolve($relative, $metadata->slug);

        return new Document(
            path: $file->getPathname(),
            relativePath: $relative,
            slug: $slug,
            metadata: $metadata,
            markdown: $body,
            html: null,
            modifiedAt: (int) $file->getMTime(),
        );
    }

    private function relativePath(SplFileInfo $file, string $basePath): string
    {
        $relative = ltrim(str_replace($basePath, '', $file->getPathname()), '/\\');

        return str_replace('\\', '/', $relative);
    }
}

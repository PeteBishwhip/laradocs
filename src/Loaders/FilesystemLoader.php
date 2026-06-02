<?php

declare(strict_types=1);

namespace Laradocs\Loaders;

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
     * @param  array<int, string>  $extensions
     * @param  array<int, string>  $ignoredPatterns
     * @param  array<string, mixed>  $metadataDefaults
     */
    public function __construct(
        private readonly Filesystem $files,
        private readonly MetadataResolver $metadataResolver,
        private readonly SlugResolver $slugResolver,
        private readonly string $path,
        private readonly array $extensions = ['md'],
        private readonly array $ignoredPatterns = [],
        private readonly array $metadataDefaults = [],
    ) {}

    public function all(): DocumentCollection
    {
        if (! $this->files->isDirectory($this->path)) {
            return new DocumentCollection;
        }

        $documents = new DocumentCollection;

        foreach ($this->files->allFiles($this->path) as $file) {
            if (! $this->shouldInclude($file)) {
                continue;
            }

            $documents->push($this->makeDocument($file));
        }

        return $documents;
    }

    public function find(string $slug): ?Document
    {
        return $this->all()->findBySlug($slug);
    }

    private function shouldInclude(SplFileInfo $file): bool
    {
        if (! in_array(strtolower($file->getExtension()), $this->extensions, true)) {
            return false;
        }

        $relative = $this->relativePath($file);

        foreach (explode('/', $relative) as $segment) {
            foreach ($this->ignoredPatterns as $pattern) {
                if (fnmatch($pattern, $segment)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function makeDocument(SplFileInfo $file): Document
    {
        $relative = $this->relativePath($file);

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

    private function relativePath(SplFileInfo $file): string
    {
        $relative = ltrim(str_replace($this->path, '', $file->getPathname()), '/\\');

        return str_replace('\\', '/', $relative);
    }
}

<?php

declare(strict_types=1);

namespace Laradocs\Routing;

use Illuminate\Support\Str;

final class SlugResolver
{
    /**
     * @readonly
     * @var string
     */
    private $strategy = 'both';
    /**
     * @readonly
     * @var string
     */
    private $indexName = '_index';
    public function __construct(string $strategy = 'both', string $indexName = '_index')
    {
        $this->strategy = $strategy;
        $this->indexName = $indexName;
    }

    /**
     * Resolve the public slug for a document.
     *
     * @param  string  $relativePath  e.g. "guide/getting-started.md"
     * @param  string|null  $metadataSlug  the front-matter "slug" value, if any
     */
    public function resolve(string $relativePath, ?string $metadataSlug = null): string
    {
        if ($this->prefersMetadata() && $metadataSlug !== null && $metadataSlug !== '') {
            return $this->normalize($metadataSlug);
        }

        return $this->fromFilename($relativePath);
    }

    /**
     * Derive a slug purely from the file path.
     */
    public function fromFilename(string $relativePath): string
    {
        $relativePath = str_replace('\\', '/', $relativePath);
        $withoutExtension = preg_replace('/\.[^.\/]+$/', '', $relativePath) ?? $relativePath;

        $segments = array_values(array_filter(explode('/', $withoutExtension), function ($s): bool {
            return $s !== '';
        }));

        // A section index file represents its parent directory.
        if ($segments !== [] && end($segments) === $this->indexName) {
            array_pop($segments);
        }

        return $this->slugSegments($segments);
    }

    private function prefersMetadata(): bool
    {
        return in_array($this->strategy, ['metadata', 'both'], true);
    }

    private function normalize(string $slug): string
    {
        $slug = str_replace('\\', '/', $slug);
        $segments = explode('/', trim($slug, '/'));

        return $this->slugSegments($segments);
    }

    /**
     * Slugify each segment and drop any that are empty or traversal markers
     * (".", "..") so a slug can never contain "//" or escape its namespace.
     *
     * @param  array<int, string>  $segments
     */
    private function slugSegments(array $segments): string
    {
        $slugged = [];

        foreach ($segments as $segment) {
            $slug = Str::slug($segment);

            if ($slug !== '') {
                $slugged[] = $slug;
            }
        }

        return implode('/', $slugged);
    }
}

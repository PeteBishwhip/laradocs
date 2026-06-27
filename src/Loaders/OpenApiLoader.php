<?php

declare(strict_types=1);

namespace Laradocs\Loaders;

use Closure;
use Illuminate\Support\Str;
use Laradocs\Contracts\DocumentContentRenderer;
use Laradocs\Contracts\DocumentLoader;
use Laradocs\Documents\Document;
use Laradocs\Documents\DocumentCollection;
use Laradocs\Documents\DocumentTree;
use Laradocs\Metadata\Metadata;
use Laradocs\OpenApi\NormalizedSpec;
use Laradocs\OpenApi\OpenApiParser;
use Laradocs\OpenApi\Operation;
use Laradocs\Support\CacheKey;

/**
 * Surfaces each OpenAPI operation as a navigable {@see Document} so endpoints
 * flow through the existing sidebar, search, sitemap and feed pipeline exactly
 * like a hand-written markdown page.
 *
 * For every spec file discovered under the active docs path the loader emits:
 *   - one *overview* document at the configured base slug, and
 *   - one *operation* document per HTTP method/path pair, slugged
 *     `{base_slug}/{tag}/{operationId|method-path}` so operations nest under
 *     their first tag in the {@see DocumentTree}.
 *
 * Each synthetic document carries empty markdown — its body is produced later by
 * a {@see DocumentContentRenderer} keyed off the
 * `metadata.extra['openapi']` marker. The document `path`/`relativePath` embed
 * both the operation key and the active locale so no two operations (and no two
 * locale variants of the same operation) ever share an HTML cache key — the
 * cache key folds in {@see Document::$path} via
 * {@see CacheKey::document()}.
 */
final class OpenApiLoader implements DocumentLoader
{
    /**
     * @param  string|Closure(): string  $path  The docs source to scan for spec
     *                                          files, re-invoked per call so a closure-backed path tracks the
     *                                          request's active version.
     * @param  array<int, string>  $files  Candidate spec filenames searched for
     *                                     inside the docs path; every match becomes a spec.
     * @param  string|Closure(): string  $activeLocale  The locale the current
     *                                                  request renders in, appended to each document path so multi-locale
     *                                                  specs do not share an HTML cache key.
     */
    public function __construct(
        private readonly OpenApiParser $parser,
        private readonly string|Closure $path,
        private readonly array $files = ['openapi.yaml', 'openapi.yml', 'openapi.json'],
        private readonly string $baseSlug = 'api',
        private readonly ?string $title = null,
        private readonly ?string $group = null,
        private readonly int $order = 0,
        private readonly string|Closure $activeLocale = '',
    ) {}

    public function all(): DocumentCollection
    {
        $documents = new DocumentCollection;
        $locale = $this->activeLocale();

        foreach ($this->specFiles() as $specPath) {
            $spec = $this->parser->parse($specPath);
            $mtime = (int) filemtime($specPath);

            $documents->push($this->overview($specPath, $spec, $mtime, $locale));

            $order = 0;

            foreach ($spec->operations() as $operation) {
                $documents->push(
                    $this->operation($specPath, $operation, $mtime, $locale, $order++),
                );
            }
        }

        return $documents;
    }

    public function find(string $slug): ?Document
    {
        return $this->all()->findBySlug($slug);
    }

    /**
     * The absolute paths of every configured spec filename present in the docs
     * source. The path is resolved lazily so a closure-backed path picks up the
     * request's active version directory.
     *
     * @return array<int, string>
     */
    private function specFiles(): array
    {
        $dir = is_string($this->path) ? $this->path : ($this->path)();

        if (! is_dir($dir)) {
            return [];
        }

        $found = [];

        foreach ($this->files as $name) {
            $candidate = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $name;

            if (is_file($candidate)) {
                $found[] = $candidate;
            }
        }

        return $found;
    }

    private function overview(string $specPath, NormalizedSpec $spec, int $mtime, string $locale): Document
    {
        $info = $spec->info();
        $title = $this->title
            ?? (isset($info['title']) && is_scalar($info['title']) ? (string) $info['title'] : 'API Reference');
        $description = isset($info['description']) && is_scalar($info['description'])
            ? (string) $info['description']
            : null;

        $metadata = Metadata::fromArray([
            'title' => $title,
            'description' => $description,
            'group' => $this->group,
            'order' => $this->order,
            'openapi' => [
                'type' => 'overview',
                'spec' => $specPath,
            ],
        ]);

        return $this->document($specPath, 'overview', $this->baseSlug, $metadata, $mtime, $locale);
    }

    private function operation(string $specPath, Operation $operation, int $mtime, string $locale, int $order): Document
    {
        $tag = $operation->tags[0] ?? 'default';
        $opSegment = $operation->operationId !== null && $operation->operationId !== ''
            ? $operation->operationId
            : $operation->method . ' ' . $operation->path;

        $slug = $this->baseSlug . '/' . Str::slug($tag) . '/' . Str::slug($opSegment);

        $opKey = $operation->operationId !== null && $operation->operationId !== ''
            ? $operation->operationId
            : strtolower($operation->method) . ' ' . $operation->path;

        $metadata = Metadata::fromArray([
            'title' => $operation->summary ?? ($operation->method . ' ' . $operation->path),
            'description' => $operation->description,
            'group' => $tag,
            'tags' => $operation->tags,
            'order' => $order,
            'openapi' => [
                'type' => 'operation',
                'spec' => $specPath,
                'op' => [
                    'method' => $operation->method,
                    'path' => $operation->path,
                    'operationId' => $operation->operationId,
                ],
            ],
        ]);

        return $this->document($specPath, $opKey, $slug, $metadata, $mtime, $locale);
    }

    /**
     * Build a synthetic document. The `path`/`relativePath` combine the spec
     * file, the operation key and the active locale so every document — and
     * every locale variant of it — is distinct, both for slug resolution and for
     * the HTML cache key derived from the path.
     */
    private function document(string $specPath, string $opKey, string $slug, Metadata $metadata, int $mtime, string $locale): Document
    {
        $reference = $specPath . '#' . $opKey . '@' . $locale;

        return new Document(
            path: $reference,
            relativePath: $reference,
            slug: $slug,
            metadata: $metadata,
            markdown: '',
            html: null,
            modifiedAt: $mtime,
            locale: $locale === '' ? null : $locale,
        );
    }

    private function activeLocale(): string
    {
        return is_string($this->activeLocale) ? $this->activeLocale : ($this->activeLocale)();
    }
}

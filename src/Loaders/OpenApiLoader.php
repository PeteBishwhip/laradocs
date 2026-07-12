<?php

declare(strict_types=1);

namespace Laradocs\Loaders;

use Closure;
use Laradocs\Contracts\DocumentContentRenderer;
use Laradocs\Contracts\DocumentLoader;
use Laradocs\Documents\Document;
use Laradocs\Documents\DocumentCollection;
use Laradocs\Documents\DocumentTree;
use Laradocs\Metadata\Metadata;
use Laradocs\OpenApi\NormalizedSpec;
use Laradocs\OpenApi\OpenApiParser;
use Laradocs\OpenApi\Operation;
use Laradocs\OpenApi\OperationSlugger;
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
     * @readonly
     * @var \Laradocs\OpenApi\OpenApiParser
     */
    private $parser;
    /**
     * @var string|Closure():string
     * @readonly
     */
    private $path;
    /**
     * @var array<int, string>
     * @readonly
     */
    private $files = ['openapi.yaml', 'openapi.yml', 'openapi.json'];
    /**
     * @readonly
     * @var string
     */
    private $baseSlug = 'api';
    /**
     * @readonly
     * @var string|null
     */
    private $title;
    /**
     * @readonly
     * @var string|null
     */
    private $group;
    /**
     * @readonly
     * @var int
     */
    private $order = 0;
    /**
     * @var string|Closure():string
     * @readonly
     */
    private $activeLocale = '';
    /**
     * @var string|Closure():string
     * @readonly
     */
    private $defaultLocale = '';
    /**
     * @param  string|Closure(): string  $path  The docs source to scan for spec
     *                                          files, re-invoked per call so a closure-backed path tracks the
     *                                          request's active version.
     * @param  array<int, string>  $files  Candidate spec filenames searched for
     *                                     inside the docs path; every match becomes a spec.
     * @param  string|Closure(): string  $activeLocale  The locale the current
     *                                                  request renders in, appended to each document path so multi-locale
     *                                                  specs do not share an HTML cache key.
     * @param  string|Closure(): string  $defaultLocale  The locale an un-suffixed
     *                                                   spec belongs to. A non-default active locale prefers a localised
     *                                                   spec (openapi.{locale}.json or {locale}/openapi.json), falling back
     *                                                   to the un-suffixed file when no translation exists.
     */
    public function __construct(OpenApiParser $parser, $path, array $files = ['openapi.yaml', 'openapi.yml', 'openapi.json'], string $baseSlug = 'api', ?string $title = null, ?string $group = null, int $order = 0, $activeLocale = '', $defaultLocale = '')
    {
        $this->parser = $parser;
        $this->path = $path;
        $this->files = $files;
        $this->baseSlug = $baseSlug;
        $this->title = $title;
        $this->group = $group;
        $this->order = $order;
        $this->activeLocale = $activeLocale;
        $this->defaultLocale = $defaultLocale;
    }

    public function all(): DocumentCollection
    {
        $documents = new DocumentCollection;
        $locale = $this->activeLocale();

        foreach ($this->specSources() as [$specPath, $canonicalPath]) {
            $spec = $this->parser->parse($specPath);
            $mtime = (int) filemtime($specPath);

            // Operation slugs come from the default-locale (canonical) spec, so a
            // translated summary in openapi.{locale}.json never changes the URL —
            // every language shares the same operation paths. The active spec's
            // own slugs are only a fallback for operations it adds on its own.
            $canonicalSpec = $canonicalPath === $specPath ? $spec : $this->parser->parse($canonicalPath);
            $slugs = OperationSlugger::resolve($spec->operations(), $canonicalSpec->operations(), $this->baseSlug);

            $documents->push($this->overview($specPath, $canonicalPath, $spec, $mtime, $locale));

            $order = 0;

            foreach ($spec->operations() as $operation) {
                $documents->push(
                    $this->operation($specPath, $operation, $mtime, $locale, $order++, $slugs[OperationSlugger::identity($operation)]),
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
     * Every configured spec present in the docs source, resolved for the active
     * locale. Each entry is `[activePath, canonicalPath]`: the active path is the
     * locale-appropriate file to render, the canonical path is the un-suffixed
     * (default-locale) file the operation slugs are derived from. The docs path
     * is resolved lazily so a closure-backed path tracks the active version.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function specSources(): array
    {
        $dir = is_string($this->path) ? $this->path : ($this->path)();

        if (! is_dir($dir)) {
            return [];
        }

        $dir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
        $locale = $this->activeLocale();
        $default = is_string($this->defaultLocale) ? $this->defaultLocale : ($this->defaultLocale)();
        $sources = [];

        foreach ($this->files as $name) {
            $active = $this->localisedSpec($dir, $name, $locale, $default);

            if ($active === null) {
                continue;
            }

            // The un-suffixed openapi.json is canonical; when only locale-specific
            // specs exist, the default locale's spec is canonical for every
            // language. Slugs derive from it so URLs stay stable across locales.
            $canonical = $this->canonicalSpec($dir, $name, $default) ?? $active;
            $sources[] = [$active, $canonical];
        }

        return $sources;
    }

    /**
     * Resolve a spec filename for the active locale. A non-default locale prefers
     * a filename-suffixed variant (openapi.fr.json) then a locale directory
     * (fr/openapi.json), mirroring content-page localisation, and finally the
     * canonical spec (un-suffixed, or the default locale's variant). Returns null
     * when nothing exists.
     */
    private function localisedSpec(string $dir, string $name, string $locale, string $default): ?string
    {
        if ($locale !== '' && $locale !== $default) {
            $variant = $this->localeVariant($dir, $name, $locale);

            if ($variant !== null) {
                return $variant;
            }
        }

        return $this->canonicalSpec($dir, $name, $default);
    }

    /**
     * The canonical spec every locale's slugs derive from: the un-suffixed
     * openapi.json when present, otherwise the default locale's variant (so a
     * spec set that ships only openapi.en.json / openapi.fr.json still shares one
     * set of URLs). Returns null when neither exists.
     */
    private function canonicalSpec(string $dir, string $name, string $default): ?string
    {
        $base = $dir . $name;

        if (is_file($base)) {
            return $base;
        }

        return $default !== '' ? $this->localeVariant($dir, $name, $default) : null;
    }

    /**
     * A locale-specific spec file for `$locale`: the filename-suffixed variant
     * (openapi.fr.json) then the locale directory (fr/openapi.json), mirroring
     * content-page localisation. Returns null when neither exists.
     */
    private function localeVariant(string $dir, string $name, string $locale): ?string
    {
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $stem = $ext === '' ? $name : (string) substr($name, 0, -(strlen($ext) + 1));
        $suffixed = $dir . ($ext === '' ? "{$stem}.{$locale}" : "{$stem}.{$locale}.{$ext}");

        if (is_file($suffixed)) {
            return $suffixed;
        }

        $inDirectory = $dir . $locale . DIRECTORY_SEPARATOR . $name;

        return is_file($inDirectory) ? $inDirectory : null;
    }

    private function overview(string $specPath, string $canonicalPath, NormalizedSpec $spec, int $mtime, string $locale): Document
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
                // The overview links to operations by their canonical slug, so it
                // needs the canonical spec to resolve them (see OperationSlugger).
                'canonicalSpec' => $canonicalPath,
            ],
        ]);

        return $this->document($specPath, 'overview', $this->baseSlug, $metadata, $mtime, $locale);
    }

    private function operation(string $specPath, Operation $operation, int $mtime, string $locale, int $order, string $slug): Document
    {
        $tag = $operation->tags[0] ?? 'default';

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
            $reference,
            $reference,
            $slug,
            $metadata,
            '',
            null,
            $mtime,
            $locale === '' ? null : $locale,
        );
    }

    private function activeLocale(): string
    {
        return is_string($this->activeLocale) ? $this->activeLocale : ($this->activeLocale)();
    }
}

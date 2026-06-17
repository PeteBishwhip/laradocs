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
     * @param  array<int, string>|Closure(): array<int, string>  $locales  Recognised
     *                                                                     content locale codes. A `page.fr.md` suffix or `fr/page.md` directory is
     *                                                                     only treated as a translation when its code appears here; an empty list
     *                                                                     disables content localisation entirely (every file loads as-is).
     * @param  string|Closure(): string  $activeLocale  The locale to serve the current
     *                                                  request in. Re-invoked per call so it tracks the per-request locale.
     * @param  string|Closure(): string  $defaultLocale  The locale an un-suffixed file
     *                                                   belongs to, and the one a missing translation falls back to.
     */
    public function __construct(
        private readonly Filesystem $files,
        private readonly MetadataResolver $metadataResolver,
        private readonly SlugResolver $slugResolver,
        private readonly string|Closure $path,
        private readonly array $extensions = ['md'],
        private readonly array $ignoredPatterns = [],
        private readonly array $metadataDefaults = [],
        private readonly array|Closure $locales = [],
        private readonly string|Closure $activeLocale = '',
        private readonly string|Closure $defaultLocale = '',
    ) {}

    public function all(): DocumentCollection
    {
        $path = $this->path();

        if (! $this->files->isDirectory($path)) {
            return new DocumentCollection;
        }

        $locales = $this->locales();
        $default = $locales === [] ? null : $this->defaultLocale();

        $documents = new DocumentCollection;

        // slug => [locale => Document]. When content localisation is off the
        // inner key is irrelevant; one document is pushed straight through so
        // behaviour is byte-for-byte identical to the un-localised loader.
        $variants = [];

        foreach ($this->files->allFiles($path) as $file) {
            if (! $this->shouldInclude($file, $path)) {
                continue;
            }

            $document = $this->makeDocument($file, $path, $locales, $default);

            if ($locales === []) {
                $documents->push($document);

                continue;
            }

            $variants[$document->slug][(string) $document->locale] = $document;
        }

        if ($locales === []) {
            return $documents;
        }

        return $this->resolveLocale($variants, $this->activeLocale(), (string) $default);
    }

    /**
     * Collapse each slug's per-locale variants down to the one document the
     * current request should serve.
     *
     * Resolution order, per slug:
     *   1. The exact active-locale translation, when present.
     *   2. The default-locale document (an un-suffixed file, or one explicitly
     *      tagged with the default code) — the fallback for a missing
     *      translation.
     *   3. Any remaining variant, so a page that exists only in some other
     *      locale is never hidden outright.
     *
     * @param  array<string, array<string, Document>>  $variants
     */
    private function resolveLocale(array $variants, string $active, string $default): DocumentCollection
    {
        $documents = new DocumentCollection;

        foreach ($variants as $byLocale) {
            $chosen = $byLocale[$active]
                ?? $byLocale[$default]
                ?? reset($byLocale);

            if ($chosen instanceof Document) {
                $documents->push($chosen);
            }
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

    /**
     * The recognised content locale codes, resolved lazily so they track the
     * published locale directories at request time.
     *
     * @return array<int, string>
     */
    private function locales(): array
    {
        return is_array($this->locales) ? $this->locales : ($this->locales)();
    }

    private function activeLocale(): string
    {
        return is_string($this->activeLocale) ? $this->activeLocale : ($this->activeLocale)();
    }

    private function defaultLocale(): string
    {
        return is_string($this->defaultLocale) ? $this->defaultLocale : ($this->defaultLocale)();
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

    /**
     * @param  array<int, string>  $locales  Recognised content locale codes.
     * @param  string|null  $default  The locale an un-suffixed file belongs to,
     *                                or null when content localisation is off.
     */
    private function makeDocument(SplFileInfo $file, string $basePath, array $locales, ?string $default): Document
    {
        $relative = $this->relativePath($file, $basePath);

        // Strip any locale marker before resolving the slug so a translation
        // shares its slug with the original (e.g. guide/intro.fr.md and
        // guide/intro.md both resolve to "guide/intro").
        [$locale, $slugPath] = $this->detectLocale($relative, $locales);

        [$matter, $body] = $this->metadataResolver->resolve($this->files->get($file->getPathname()));

        $metadata = Metadata::fromArray($matter, $this->metadataDefaults);

        $slug = $this->slugResolver->resolve($slugPath, $metadata->slug);

        return new Document(
            path: $file->getPathname(),
            relativePath: $relative,
            slug: $slug,
            metadata: $metadata,
            markdown: $body,
            html: null,
            modifiedAt: (int) $file->getMTime(),
            locale: $locale ?? $default,
        );
    }

    /**
     * Detect a document's content locale and return its locale-stripped path.
     *
     * Two conventions are recognised, checked in order:
     *   1. Directory prefix — `fr/guide/intro.md`  → ['fr', 'guide/intro.md']
     *   2. Filename suffix  — `guide/intro.fr.md`  → ['fr', 'guide/intro.md']
     *
     * Only codes present in $locales count, so an ordinary file such as
     * `release.2.0.md` or a content directory that merely happens to share a
     * locale's name is never mistaken for a translation. Returns [null, $path]
     * when no recognised locale is present.
     *
     * @param  array<int, string>  $locales
     * @return array{0: string|null, 1: string}
     */
    private function detectLocale(string $relativePath, array $locales): array
    {
        if ($locales === []) {
            return [null, $relativePath];
        }

        $path = str_replace('\\', '/', $relativePath);

        return $this->detectDirectoryLocale($path, $locales)
            ?? $this->detectSuffixLocale($path, $locales)
            ?? [null, $path];
    }

    /**
     * Detect a locale encoded as a leading directory segment: "{locale}/rest/of/path".
     *
     * @param  array<int, string>  $locales
     * @return array{0: string, 1: string}|null
     */
    private function detectDirectoryLocale(string $path, array $locales): ?array
    {
        $slash = strpos($path, '/');

        if ($slash !== false && in_array(substr($path, 0, $slash), $locales, true)) {
            return [substr($path, 0, $slash), substr($path, $slash + 1)];
        }

        return null;
    }

    /**
     * Detect a locale encoded as a filename suffix: "name.{locale}.ext".
     * Requires a base name and an extension on either side of the locale code
     * so that ordinary dotted filenames (e.g. "release-2.0.md") never match.
     *
     * @param  array<int, string>  $locales
     * @return array{0: string, 1: string}|null
     */
    private function detectSuffixLocale(string $path, array $locales): ?array
    {
        $dir = '';
        $file = $path;

        if (($lastSlash = strrpos($path, '/')) !== false) {
            $dir = substr($path, 0, $lastSlash + 1);
            $file = substr($path, $lastSlash + 1);
        }

        if (preg_match('/^(.+)\.([A-Za-z0-9_-]+)\.([^.]+)$/', $file, $m) && in_array($m[2], $locales, true)) {
            return [$m[2], $dir . $m[1] . '.' . $m[3]];
        }

        return null;
    }

    private function relativePath(SplFileInfo $file, string $basePath): string
    {
        $relative = ltrim(str_replace($basePath, '', $file->getPathname()), '/\\');

        return str_replace('\\', '/', $relative);
    }
}

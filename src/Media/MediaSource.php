<?php

declare(strict_types=1);

namespace Laradocs\Media;

use Illuminate\Contracts\Filesystem\Filesystem as Disk;
use Illuminate\Support\Facades\Storage;
use Laradocs\Documents\Document;
use Laradocs\Support\Config;
use Laradocs\Support\Version;
use Symfony\Component\Mime\MimeTypes;

/**
 * Decides where an image in a document actually lives.
 *
 * Markdown is written next to the files it points at — `![](diagram.png)` or
 * `![](../img/diagram.png)` — but the docs directory is not public, so those
 * sources resolve to nothing once rendered. Three strategies cover the ways a
 * project stores them:
 *
 *   public    the source is already a public URL or a path under /public and is
 *             left exactly as authored. The default, so existing sites are
 *             unaffected.
 *   relative  the file sits beside the markdown, inside the docs directory.
 *   disk      the file lives on a configured filesystem disk.
 *
 * Nothing outside the source is reachable: a `..` that climbs past the root is
 * refused, and only the configured extensions are ever resolved.
 */
final class MediaSource
{
    public const PUBLIC = 'public';

    public const RELATIVE = 'relative';

    public const DISK = 'disk';

    /**
     * Media types served when none are configured, in the notation Laravel's
     * `mimetypes` validation rule uses. Documents fall outside all of them:
     * markdown is text/markdown, and the docs routes are what render it.
     *
     * @var list<string>
     */
    private const DEFAULT_TYPES = ['image/*', 'video/*', 'audio/*', 'application/pdf'];

    public function strategy(): string
    {
        $strategy = strtolower(Config::string('laradocs.media.source', self::PUBLIC));

        return in_array($strategy, [self::PUBLIC, self::RELATIVE, self::DISK], true)
            ? $strategy
            : self::PUBLIC;
    }

    public function enabled(): bool
    {
        return $this->strategy() !== self::PUBLIC;
    }

    /**
     * The path a source resolves to within the active strategy's root, or null
     * when it resolves to nothing servable.
     *
     * @param  Document|null  $document  The page the source was written on, which
     *                                   is what a relative path is relative to.
     */
    public function resolve(string $source, ?Document $document = null): ?string
    {
        $source = trim($source);

        if ($source === '' || ! $this->isLocal($source) || ! $this->isServable($source)) {
            return null;
        }

        $path = $this->strategy() === self::RELATIVE
            ? $this->relativeTo($source, $document)
            : $this->normalise($source);

        if ($path === null || ! $this->exists($path)) {
            return null;
        }

        return $path;
    }

    public function exists(string $path): bool
    {
        if (! $this->isServable($path)) {
            return false;
        }

        return $this->strategy() === self::DISK
            ? $this->disk()->exists($path)
            : is_file($this->absolute($path));
    }

    /**
     * The absolute path of a resolved file. Disk-backed sources have none.
     */
    public function absolute(string $path): string
    {
        return rtrim(Version::docsPath(), '/') . '/' . ltrim($path, '/');
    }

    public function disk(): Disk
    {
        return Storage::disk(Config::nullableString('laradocs.media.disk'));
    }

    /**
     * Whether a path looks like a configured media type, judged by what its
     * extension maps to. Cheap enough to run while rewriting a page, since it
     * reads no files.
     */
    public function isServable(string $path): bool
    {
        $extension = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?? $path, PATHINFO_EXTENSION));

        if ($extension === '') {
            return false;
        }

        foreach (MimeTypes::getDefault()->getMimeTypes($extension) as $mime) {
            if ($this->allows($mime)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a media type is one the docs serve. Patterns are matched the way
     * the `mimetypes` validation rule reads: an exact type, or a group with a
     * wildcard subtype such as image/*.
     */
    public function allows(string $mime): bool
    {
        $mime = strtolower(trim(explode(';', $mime)[0]));

        if ($mime === '') {
            return false;
        }

        /** @var list<string> $types */
        $types = Config::array('laradocs.media.types', self::DEFAULT_TYPES);

        foreach ($types as $pattern) {
            if (fnmatch(strtolower(trim($pattern)), $mime)) {
                return true;
            }
        }

        return false;
    }

    /**
     * What a resolved file actually contains, as opposed to what its name
     * claims. Null when it cannot be determined.
     */
    public function mimeType(string $path): ?string
    {
        if ($this->strategy() === self::DISK) {
            $mime = $this->disk()->mimeType($path);

            return $mime === false ? null : $mime;
        }

        return MimeTypes::getDefault()->guessMimeType($this->absolute($path));
    }

    /**
     * A source that points somewhere else — another host, a data URI, or the
     * site root — is not ours to resolve.
     */
    private function isLocal(string $source): bool
    {
        return ! str_starts_with($source, '/')
            && ! str_starts_with($source, '#')
            && preg_match('#^[a-z][a-z0-9+.-]*:#i', $source) !== 1
            && ! str_starts_with($source, '//');
    }

    /**
     * Resolve a source against the directory of the page it was written on.
     */
    private function relativeTo(string $source, ?Document $document): ?string
    {
        $directory = $document === null ? '' : trim(dirname($document->relativePath), '.\/');

        return $this->normalise($directory === '' ? $source : $directory . '/' . $source);
    }

    /**
     * Collapse `.` and `..` and refuse anything that climbs past the root.
     */
    private function normalise(string $path): ?string
    {
        $segments = [];

        foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment !== '..') {
                $segments[] = $segment;

                continue;
            }

            if ($segments === []) {
                return null;
            }

            array_pop($segments);
        }

        return $segments === [] ? null : implode('/', $segments);
    }
}

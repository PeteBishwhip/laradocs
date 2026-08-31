<?php

declare(strict_types=1);

namespace Laradocs\Routing;

use DOMDocument;
use DOMElement;
use Laradocs\Documents\Document;
use Laradocs\Media\MediaRewriter;
use Laradocs\Media\MediaSource;
use Laradocs\Support\Config;
use Laradocs\Support\Html;

/**
 * Points links between documents at the routes that serve them.
 *
 * Markdown is written to be readable where it lives, so a cross-reference is
 * spelled as a path to the file: `[Getting started](getting-started.md)`, or
 * `[Intro](../guide/intro.md)`. That is what keeps a docs directory navigable
 * in an editor and on a repository host. Rendered, it is also a link to a file
 * the docs routes never serve, so every one of them 404s.
 *
 * This resolves each one to the slug the target document is published under.
 * It runs on the way out of the HTML cache rather than inside it, for the same
 * reason {@see MediaRewriter} does: the document is known here,
 * so a relative path resolves against the page that wrote it.
 *
 * Only relative links to a document extension are touched. Absolute URLs, mail
 * addresses, root-relative paths, bare fragments and links that already point
 * at a slug are left exactly as authored, as is anything that climbs out of the
 * docs directory.
 */
final class DocumentLinkRewriter
{
    public function __construct(
        private readonly SlugResolver $slugs,
    ) {}

    public function rewrite(string $html, ?Document $document = null): string
    {
        // Html::mutate already returns an empty fragment untouched.
        return Html::mutate($html, function (DOMDocument $dom, DOMElement $body) use ($document): void {
            /** @var DOMElement $anchor */
            foreach (iterator_to_array($body->getElementsByTagName('a')) as $anchor) {
                $this->point($anchor, $document);
            }
        });
    }

    private function point(DOMElement $anchor, ?Document $document): void
    {
        $href = $this->resolve($anchor->getAttribute('href'), $document);

        if ($href !== null) {
            $anchor->setAttribute('href', $href);
        }
    }

    /**
     * The URL a document link resolves to, or null to leave the href alone.
     */
    private function resolve(string $href, ?Document $document): ?string
    {
        if ($href === '' || ! $this->isLocal($href)) {
            return null;
        }

        // A fragment or query belongs to the link, not to the file it names.
        $split = strcspn($href, '#?');
        $path = substr($href, 0, $split);
        $suffix = substr($href, $split);

        if (! $this->isDocument($path)) {
            return null;
        }

        $relativePath = $this->relativeTo($path, $document);

        if ($relativePath === null) {
            return null;
        }

        return DocumentUrl::toSlug($this->slugs->fromFilename($relativePath)) . $suffix;
    }

    /**
     * Whether the href addresses a file beside this one, rather than a scheme,
     * a host, the site root or a position on this page.
     */
    private function isLocal(string $href): bool
    {
        return ! str_starts_with($href, '/')
            && ! str_starts_with($href, '#')
            && ! str_starts_with($href, '//')
            && preg_match('#^[a-z][a-z0-9+.-]*:#i', $href) !== 1;
    }

    private function isDocument(string $path): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === '') {
            return false;
        }

        /** @var list<string> $extensions */
        $extensions = array_values(array_filter(
            Config::array('laradocs.docs.extensions', ['md', 'markdown']),
            static fn (mixed $value): bool => is_string($value),
        ));

        foreach ($extensions as $known) {
            if (strtolower(ltrim($known, '.')) === $extension) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve a path against the directory of the page it was written on,
     * collapsing `.` and `..` and refusing anything that climbs past the docs
     * root — the same containment {@see MediaSource} applies.
     */
    private function relativeTo(string $path, ?Document $document): ?string
    {
        $directory = $document === null ? '' : trim(dirname($document->relativePath), '.\/');
        $segments = [];

        foreach (explode('/', str_replace('\\', '/', $directory === '' ? $path : $directory . '/' . $path)) as $segment) {
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

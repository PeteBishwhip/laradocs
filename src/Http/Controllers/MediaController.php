<?php

declare(strict_types=1);

namespace Laradocs\Http\Controllers;

use Laradocs\Media\MediaSource;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Serves the images, video and other files that sit beside the markdown.
 *
 * Documents are rendered by {@see DocsController}; the files they point at are
 * invisible without this, since the docs directory is not public and a disk may
 * not be either. What reaches a reader is bounded twice over: the path must
 * resolve inside the configured source, and both its extension and its actual
 * contents must be one of the configured media types, so neither a traversal, a
 * request for the markdown, nor a script wearing an image extension gets
 * anywhere.
 *
 * Hotlinking is answered by `media.signed`, which the route enforces.
 */
final class MediaController
{
    public function __construct(
        private readonly MediaSource $source,
    ) {}

    public function __invoke(string $path): SymfonyResponse
    {
        if (! $this->source->enabled()) {
            abort(404);
        }

        // Resolve without a document: a media URL is already root-relative, so
        // there is nothing left to resolve it against.
        $resolved = $this->source->resolve($path);

        if ($resolved === null) {
            abort(404);
        }

        // The extension said this was media; the file itself has to agree, so a
        // script renamed to .png is refused rather than handed over.
        $mime = $this->source->mimeType($resolved);

        if ($mime !== null && ! $this->source->allows($mime)) {
            abort(404);
        }

        $headers = ['Cache-Control' => 'public, max-age=0, must-revalidate'];

        if ($this->source->strategy() === MediaSource::DISK) {
            return $this->source->disk()->response($resolved, null, $headers);
        }

        return response()
            ->file($this->source->absolute($resolved), $headers)
            ->setAutoLastModified()
            ->setAutoEtag();
    }
}

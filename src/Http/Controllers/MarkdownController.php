<?php

declare(strict_types=1);

namespace Laradocs\Http\Controllers;

use Illuminate\Http\Response;
use Laradocs\Laradocs;
use Laradocs\Support\Version;

final class MarkdownController
{
    public function __construct(
        private readonly Laradocs $laradocs,
    ) {}

    public function __invoke(string $path): Response
    {
        // Strip the version prefix the same way DocsController::show does, so
        // the slug resolves against that version.
        $slug = $path;
        $version = Version::current();

        if ($version !== null && str_starts_with($slug . '/', $version . '/')) {
            $slug = ltrim(substr($slug, strlen($version)), '/');
        }

        $markdown = $this->laradocs->markdown($slug);

        if ($markdown === null) {
            abort(404);
        }

        return new Response($markdown, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
        ]);
    }
}

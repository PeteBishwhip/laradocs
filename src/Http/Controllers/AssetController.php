<?php

declare(strict_types=1);

namespace Laradocs\Http\Controllers;

use Illuminate\Http\Response;

final class AssetController
{
    /**
     * @var array<string, string>
     */
    private const ASSETS = [
        'laradocs.css' => 'text/css',
        'laradocs.js' => 'application/javascript',
        // The AI chat widget ships separately from the theme so it can be
        // dropped into a page this package does not own without the docs
        // stylesheet restyling that page, or the docs script touching it.
        'laradocs-ai.css' => 'text/css',
        'laradocs-ai.js' => 'application/javascript',
    ];

    public function __invoke(string $file): Response
    {
        if (! isset(self::ASSETS[$file])) {
            abort(404);
        }

        $path = __DIR__ . '/../../../resources/dist/' . $file;

        if (! is_file($path)) {
            abort(404);
        }

        return new Response((string) file_get_contents($path), 200, [
            'Content-Type' => self::ASSETS[$file],
        ]);
    }
}

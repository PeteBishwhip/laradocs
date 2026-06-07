<?php

declare(strict_types=1);

namespace Laradocs\Http\Controllers;

use Illuminate\Http\Response;
use Laradocs\Laradocs;

final class SitemapController
{
    public function __construct(
        private readonly Laradocs $laradocs,
    ) {}

    public function __invoke(): Response
    {
        return new Response($this->laradocs->sitemap(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}

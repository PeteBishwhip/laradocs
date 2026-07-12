<?php

declare(strict_types=1);

namespace Laradocs\Http\Controllers;

use Illuminate\Http\Response;
use Laradocs\Laradocs;

final class SitemapController
{
    /**
     * @readonly
     * @var \Laradocs\Laradocs
     */
    private $laradocs;
    public function __construct(Laradocs $laradocs)
    {
        $this->laradocs = $laradocs;
    }

    public function __invoke(): Response
    {
        return new Response($this->laradocs->sitemap(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}

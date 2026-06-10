<?php

declare(strict_types=1);

namespace Laradocs\Http\Controllers;

use Illuminate\Http\Response;
use Laradocs\Laradocs;
use Laradocs\Routing\DocumentUrl;
use Laradocs\Support\Config;

final class FeedController
{
    public function __construct(
        private readonly Laradocs $laradocs,
    ) {}

    public function __invoke(): Response
    {
        $format = Config::string('laradocs.feed.format', 'rss');
        $limit = Config::int('laradocs.feed.limit', 20);
        $title = Config::string('laradocs.ui.brand.title', 'Documentation');

        $xml = $this->laradocs->feed($format, $limit, DocumentUrl::feed(), $title);

        $contentType = $format === 'atom'
            ? 'application/atom+xml; charset=UTF-8'
            : 'application/rss+xml; charset=UTF-8';

        return new Response($xml, 200, ['Content-Type' => $contentType]);
    }
}

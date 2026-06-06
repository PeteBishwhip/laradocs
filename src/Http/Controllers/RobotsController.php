<?php

declare(strict_types=1);

namespace Laradocs\Http\Controllers;

use Illuminate\Http\Response;
use Laradocs\Routing\DocumentUrl;
use Laradocs\Routing\RobotsBuilder;
use Laradocs\Support\Config;

final class RobotsController
{
    public function __invoke(): Response
    {
        $enabled = Config::bool('laradocs.enabled', true);

        $body = (new RobotsBuilder)->build(
            $enabled,
            Config::array('laradocs.robots.rules'),
            $enabled ? DocumentUrl::sitemap() : null,
        );

        return new Response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}

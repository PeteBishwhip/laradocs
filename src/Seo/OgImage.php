<?php

declare(strict_types=1);

namespace Laradocs\Seo;

use Laradocs\Contracts\OgImageGenerator;
use Laradocs\Support\Config;

/**
 * Small helper around the generated-image feature toggle.
 *
 * Generation is "available" only when it's enabled in config *and* a generator
 * is actually bound — the package binds {@see TheOgImageGenerator} when
 * simonhamp/the-og is installed, and consumers may bind their own. Keeping both
 * checks here means the SEO layer never advertises an og:image URL that the
 * route can't fulfil.
 */
final class OgImage
{
    public static function enabled(): bool
    {
        return Config::bool('laradocs.seo.og_image.enabled', true)
            && app()->bound(OgImageGenerator::class);
    }
}

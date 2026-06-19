<?php

declare(strict_types=1);

namespace Laradocs\Contracts;

use Laradocs\Seo\OgImageData;
use Laradocs\Seo\TheOgImageGenerator;

/**
 * Renders an Open Graph / social card image for a documentation page.
 *
 * The package ships {@see TheOgImageGenerator} as the default
 * implementation (powered by simonhamp/the-og). Bind your own implementation
 * in a service provider — or set `laradocs.seo.og_image.enabled` to false — to
 * take full control of how cards are produced.
 */
interface OgImageGenerator
{
    /**
     * Render a social card and return the raw PNG bytes.
     */
    public function generate(OgImageData $data): string;
}

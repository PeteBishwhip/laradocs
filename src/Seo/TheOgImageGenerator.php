<?php

declare(strict_types=1);

namespace Laradocs\Seo;

use Laradocs\Contracts\OgImageGenerator;
use SimonHamp\TheOg\Image;
use SimonHamp\TheOg\Theme;
use Throwable;

/**
 * Default {@see OgImageGenerator} backed by simonhamp/the-og.
 *
 * Renders a 1200×630 card using the page title, description and the site's
 * accent colour. A configured brand logo is placed as a watermark when one is
 * available; an unreadable logo is silently skipped so a broken asset can never
 * take down image generation.
 */
final class TheOgImageGenerator implements OgImageGenerator
{
    public function generate(OgImageData $data): string
    {
        try {
            return $this->build($data, true)->toString();
        } catch (Throwable $e) {
            // The logo is the only input that reaches out to the filesystem or
            // network, so it's the likely culprit. Retry without it before
            // surfacing the failure.
            if ($data->logo === null || trim($data->logo) === '') {
                throw $e;
            }

            return $this->build($data, false)->toString();
        }
    }

    private function build(OgImageData $data, bool $withLogo): Image
    {
        // The theme must be set first: theme() replaces the underlying theme
        // object, so any accent/background overrides applied before it would be
        // discarded.
        $image = (new Image)
            ->theme(Theme::tryFrom(strtolower(trim($data->theme))) ?? Theme::Light)
            ->title($data->title)
            ->accentColor($data->accentColor);

        if ($data->description !== null && trim($data->description) !== '') {
            $image->description($data->description);
        }

        if ($data->url !== null && trim($data->url) !== '') {
            $image->url($data->url);
        }

        if ($data->backgroundColor !== null && trim($data->backgroundColor) !== '') {
            $image->backgroundColor($data->backgroundColor);
        }

        if ($withLogo && $data->logo !== null && trim($data->logo) !== '') {
            $image->watermark($data->logo);
        }

        return $image;
    }
}

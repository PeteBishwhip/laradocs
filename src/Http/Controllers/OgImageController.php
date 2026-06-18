<?php

declare(strict_types=1);

namespace Laradocs\Http\Controllers;

use Closure;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Laradocs\Contracts\OgImageGenerator;
use Laradocs\Documents\Document;
use Laradocs\Laradocs;
use Laradocs\Routing\DocumentUrl;
use Laradocs\Seo\OgImage;
use Laradocs\Seo\OgImageData;
use Laradocs\Seo\SeoFactory;
use Laradocs\Support\Config;
use Laradocs\Support\Version;

/**
 * Serves the generated Open Graph card for a page.
 *
 * Cards are rendered on first request and cached (keyed by the page's slug and
 * modification time) so subsequent hits — and every crawler — are cheap. A page
 * that declares its own image is redirected to it: front-matter trumps
 * generation, here as well as in the meta tags.
 */
final readonly class OgImageController
{
    public function __construct(
        private Laradocs $laradocs,
        private SeoFactory $seo,
    ) {}

    public function __invoke(?string $path = null): Response|RedirectResponse
    {
        if (! OgImage::enabled()) {
            abort(404);
        }

        $document = $this->resolve($path);

        if ($document instanceof Document && ($explicit = $this->seo->explicitImage($document)) !== null) {
            return redirect()->to($explicit);
        }

        $data = $document instanceof Document
            ? OgImageData::fromDocument($document, DocumentUrl::toSlug($document->slug))
            : OgImageData::forPage(
                Config::nullableString('laradocs.seo.site_name')
                    ?? Config::string('laradocs.ui.brand.title', 'Documentation'),
                Config::nullableString('laradocs.seo.description'),
                DocumentUrl::index(),
            );

        $bytes = $this->remember($document, fn (): string => app(OgImageGenerator::class)->generate($data));

        return new Response($bytes, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=' . $this->ttl(),
        ]);
    }

    /**
     * Resolve the document a card is being requested for. A null/empty path is
     * the landing page (no backing document); anything else must resolve to a
     * real document or 404, mirroring the show route's version handling.
     */
    private function resolve(?string $path): ?Document
    {
        $slug = trim((string) $path, '/');

        $version = Version::current();

        if ($version !== null && str_starts_with($slug . '/', $version . '/')) {
            $slug = ltrim(substr($slug, strlen($version)), '/');
        }

        if ($slug === '') {
            return $this->laradocs->home();
        }

        $document = $this->laradocs->find($slug);

        if (! $document instanceof Document) {
            abort(404);
        }

        return $document;
    }

    /**
     * Cache the rendered bytes, keyed by slug + modification time + a branding
     * fingerprint so a content edit or theme change busts the card. Respects the
     * package cache toggle so generation can be exercised without a cache.
     *
     * @param  Closure(): string  $callback
     */
    private function remember(?Document $document, Closure $callback): string
    {
        if (! Config::bool('laradocs.cache.enabled', true)) {
            return $callback();
        }

        $store = app(CacheFactory::class)->store(Config::nullableString('laradocs.cache.store'));

        return $store->remember($this->cacheKey($document), $this->ttl(), $callback);
    }

    private function cacheKey(?Document $document): string
    {
        $prefix = Config::string('laradocs.cache.key_prefix', 'laradocs');

        $fingerprint = hash('sha256', implode('|', [
            $document instanceof Document ? $document->slug : '',
            $document instanceof Document ? (string) $document->modifiedAt : '',
            Config::string('laradocs.ui.accent', ''),
            Config::string('laradocs.seo.og_image.theme', ''),
            Config::nullableString('laradocs.seo.og_image.background_color') ?? '',
            Config::nullableString('laradocs.ui.brand.title') ?? '',
            Config::nullableString('laradocs.ui.brand.logo') ?? '',
        ]));

        return "{$prefix}:og:{$fingerprint}";
    }

    private function ttl(): int
    {
        return Config::int('laradocs.seo.og_image.cache_ttl', 60 * 60 * 24 * 30);
    }
}

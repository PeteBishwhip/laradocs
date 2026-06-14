<?php

declare(strict_types=1);

namespace Laradocs\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Laradocs\Documents\Tag;
use Laradocs\Laradocs;
use Laradocs\Seo\SeoFactory;
use Laradocs\Support\Config;
use RalphJSmit\Laravel\SEO\Support\SEOData;

/**
 * Renders the auto-generated tag index pages: a global "/tags" listing and a
 * per-tag "/tag/{tag}" listing.
 *
 * Real documents always win. Both routes sit ahead of the catch-all show
 * route, so before rendering a listing we check whether a document already
 * occupies that exact slug and, if so, hand the request to the normal
 * document controller. That keeps a page authored at `tags.md` (or
 * `tag/anything.md`) reachable instead of being shadowed by this feature.
 */
final class TagController
{
    public function __construct(
        private readonly Laradocs $laradocs,
        private readonly SeoFactory $seo,
        private readonly DocsController $docs,
    ) {}

    /**
     * The global index of every tag.
     */
    public function index(): View|RedirectResponse
    {
        $slug = self::indexSlug();

        if ($this->documentExistsAt($slug)) {
            return $this->docs->show($slug);
        }

        $title = __('laradocs::laradocs.tags.index_title');

        return view('laradocs::tags.index', array_merge(
            $this->chrome($slug),
            [
                'tags' => $this->laradocs->tags(),
                'title' => $title,
                'seo' => $this->seo($title, __('laradocs::laradocs.tags.index_intro')),
            ],
        ));
    }

    /**
     * The pages carrying a single tag.
     */
    public function show(string $tag): View|RedirectResponse
    {
        $path = self::prefix() . '/' . $tag;

        if ($this->documentExistsAt($path)) {
            return $this->docs->show($path);
        }

        $resolved = $this->laradocs->tag($tag);

        if (! $resolved instanceof Tag) {
            abort(404);
        }

        $title = __('laradocs::laradocs.tags.show_title', ['tag' => $resolved->label]);

        return view('laradocs::tags.show', array_merge(
            $this->chrome($path),
            [
                'tag' => $resolved,
                'title' => $title,
                'seo' => $this->seo($title),
            ],
        ));
    }

    /**
     * The configured slug of the global tag index. Defaults to "tags".
     */
    public static function indexSlug(): string
    {
        return trim(Config::string('laradocs.tags.index', 'tags'), '/');
    }

    /**
     * The configured URL prefix for individual tag pages. Defaults to "tag".
     */
    public static function prefix(): string
    {
        return trim(Config::string('laradocs.tags.prefix', 'tag'), '/');
    }

    /**
     * Shared view data every tag page needs: the navigation tree, the active
     * slug (which intentionally matches no document) and rendered variables.
     *
     * @return array<string, mixed>
     */
    private function chrome(string $activeSlug): array
    {
        return [
            'tree' => $this->laradocs->tree(),
            'activeSlug' => $activeSlug,
            'variables' => $this->laradocs->variableValues(),
            'xCard' => $this->seoEnabled() ? $this->seo->xCard() : null,
        ];
    }

    private function documentExistsAt(string $slug): bool
    {
        return $this->laradocs->all()->findBySlug($slug) !== null;
    }

    private function seo(string $title, ?string $description = null): ?SEOData
    {
        return $this->seoEnabled() ? $this->seo->forPage($title, $description) : null;
    }

    /**
     * Whether to generate SEO meta tags for this response. Disabled via config,
     * or implicitly when the SEO package isn't installed.
     */
    private function seoEnabled(): bool
    {
        return Config::bool('laradocs.seo.enabled', true)
            && class_exists(SEOData::class);
    }
}

<?php

declare(strict_types=1);

namespace Laradocs\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Laradocs\Documents\Document;
use Laradocs\Laradocs;
use Laradocs\Routing\DocumentUrl;
use Laradocs\Seo\SeoFactory;
use Laradocs\Support\Config;
use Laradocs\Support\Navigation;
use Laradocs\Toc\TableOfContents;
use RalphJSmit\Laravel\SEO\Support\SEOData;

final class DocsController
{
    public function __construct(
        private readonly Laradocs $laradocs,
        private readonly SeoFactory $seo,
    ) {}

    public function index(): View|RedirectResponse
    {
        $document = $this->laradocs->home();

        if ($document === null) {
            $seoEnabled = $this->seoEnabled();
            $seo = $seoEnabled ? $this->seo->forPage(Config::nullableString('laradocs.ui.brand.title')) : null;

            return view('laradocs::empty', [
                'tree' => $this->laradocs->tree(),
                'activeSlug' => '',
                'variables' => $this->laradocs->variableValues(),
                'seo' => $seo,
                'xCard' => $seoEnabled ? $this->seo->xCard() : null,
            ]);
        }

        return $this->view($document);
    }

    public function show(string $path): View|RedirectResponse
    {
        $slug = trim($path, '/');

        // When multi-version middleware strips the version prefix the remainder
        // may be empty (e.g. /docs/v1/ → slug ''). Delegate to index() so the
        // version root shows the same landing document as the docs home page.
        if ($slug === '') {
            return $this->index();
        }

        $document = $this->laradocs->find($slug);

        if ($document === null) {
            abort(404);
        }

        if (($redirect = $document->redirect()) !== null) {
            return redirect()->to($this->resolveRedirect($redirect));
        }

        return $this->view($document);
    }

    private function view(Document $document): View
    {
        $tree = $this->laradocs->tree();
        $navigation = $tree->navigation();
        [$previous, $next] = Navigation::siblings($navigation, $document->slug);

        $toc = TableOfContents::fromHtml(
            $document->html ?? '',
            Config::int('laradocs.parser.toc.min_level', 2),
            Config::int('laradocs.parser.toc.max_level', 3),
        );

        $breadcrumbs = Navigation::breadcrumbs($navigation, $document->slug);

        $seoEnabled = $this->seoEnabled();
        $seo = $seoEnabled ? $this->seo->forDocument($document, $breadcrumbs) : null;

        return view('laradocs::show', [
            'document' => $document,
            'tree' => $tree,
            'activeSlug' => $document->slug,
            'navigation' => $navigation,
            'breadcrumbs' => $breadcrumbs,
            'previous' => $previous,
            'next' => $next,
            'toc' => $toc,
            'variables' => $this->laradocs->variableValues(),
            'seo' => $seo,
            'xCard' => $seoEnabled ? $this->seo->xCard() : null,
        ]);
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

    private function resolveRedirect(string $target): string
    {
        if (str_starts_with($target, 'http://') || str_starts_with($target, 'https://')) {
            return $target;
        }

        return DocumentUrl::toSlug($target);
    }
}

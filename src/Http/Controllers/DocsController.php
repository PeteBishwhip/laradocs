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
use Laradocs\Support\Version;
use Laradocs\Toc\TableOfContents;
use Laradocs\Seo\SeoData;

final class DocsController
{
    /**
     * @readonly
     * @var \Laradocs\Laradocs
     */
    private $laradocs;
    /**
     * @readonly
     * @var \Laradocs\Seo\SeoFactory
     */
    private $seo;
    public function __construct(Laradocs $laradocs, SeoFactory $seo)
    {
        $this->laradocs = $laradocs;
        $this->seo = $seo;
    }

    /**
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    public function index()
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

    /**
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    public function show(string $path)
    {
        $slug = trim($path, '/');

        // With multi-version docs the URL carries a version prefix (e.g.
        // /docs/v2/getting-started). The path is already version-scoped by the
        // middleware, so strip the prefix to resolve the slug against that
        // version's document set. A bare version root (/docs/v2) leaves an
        // empty slug, which falls through to the version's landing page.
        $version = Version::current();

        if ($version !== null && strncmp($slug . '/', $version . '/', strlen($version . '/')) === 0) {
            $slug = ltrim((string) substr($slug, strlen($version)), '/');
        }

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
            && class_exists(SeoData::class);
    }

    private function resolveRedirect(string $target): string
    {
        if (strncmp($target, 'http://', strlen('http://')) === 0 || strncmp($target, 'https://', strlen('https://')) === 0) {
            return $target;
        }

        return DocumentUrl::toSlug($target);
    }
}

<?php

declare(strict_types=1);

namespace Laradocs\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Laradocs\Documents\Document;
use Laradocs\Laradocs;
use Laradocs\Support\Config;
use Laradocs\Support\Navigation;
use Laradocs\Toc\TableOfContents;

final class DocsController
{
    public function __construct(
        private readonly Laradocs $laradocs,
    ) {}

    public function index(): View|RedirectResponse
    {
        $document = $this->laradocs->home();

        if ($document === null) {
            return view('laradocs::empty', [
                'tree' => $this->laradocs->tree(),
                'activeSlug' => '',
                'variables' => $this->laradocs->variableValues(),
            ]);
        }

        return $this->view($document);
    }

    public function show(string $path): View|RedirectResponse
    {
        $slug = trim($path, '/');

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

        return view('laradocs::show', [
            'document' => $document,
            'tree' => $tree,
            'activeSlug' => $document->slug,
            'navigation' => $navigation,
            'breadcrumbs' => Navigation::breadcrumbs($navigation, $document->slug),
            'previous' => $previous,
            'next' => $next,
            'toc' => $toc,
            'variables' => $this->laradocs->variableValues(),
        ]);
    }

    private function resolveRedirect(string $target): string
    {
        if (str_starts_with($target, 'http://') || str_starts_with($target, 'https://')) {
            return $target;
        }

        return route('laradocs.show', ['path' => trim($target, '/')]);
    }
}

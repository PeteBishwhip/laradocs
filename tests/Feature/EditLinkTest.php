<?php

declare(strict_types=1);

beforeEach(function () {
    config()->set('laradocs.ui.edit.url', 'https://github.com/me/app/edit/main/docs/{file}');
});

/**
 * Render the edit-link partial against a stub document carrying the given
 * relativePath, returning the rendered HTML.
 */
function renderEditLink(string $relativePath): string
{
    $document = new class($relativePath)
    {
        /**
         * @var string
         */
        public $relativePath;
        public function __construct(string $relativePath)
        {
            $this->relativePath = $relativePath;
        }
    };

    return view('laradocs::partials.edit-link', ['document' => $document])->render();
}

it('builds a clean edit url for a real markdown file', function () {
    $html = renderEditLink('guide/intro.md');

    expect($html)->toContain('https://github.com/me/app/edit/main/docs/guide/intro.md');
});

it('strips the #fragment from a synthetic openapi operation path', function () {
    // OpenApiLoader encodes operation identity as `{specPath}#{opKey}@{locale}`.
    $html = renderEditLink('api/openapi.yaml#get-pets@en');

    expect($html)
        ->toContain('docs/api/openapi.yaml"')
        ->and($html)->not->toContain('#get-pets')
        ->and($html)->not->toContain('@en');
});

it('strips the #fragment even with no locale suffix', function () {
    $html = renderEditLink('api/openapi.yaml#overview');

    expect($html)
        ->toContain('docs/api/openapi.yaml"')
        ->and($html)->not->toContain('#overview');
});

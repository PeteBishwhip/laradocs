<?php

declare(strict_types=1);

use Laradocs\Contracts\DocumentParser;
use Laradocs\Laradocs;

function render(string $markdown): string
{
    return app(DocumentParser::class)->parse($markdown);
}

it('interpolates known variables and blanks unknown ones', function () {
    app(Laradocs::class)->share('product', 'Laradocs');

    expect(render('Welcome to {{ product }}.'))->toContain('Welcome to Laradocs.')
        ->and(render('Hello {{ missing }}!'))->toContain('Hello !');
});

it('leaves variables inside code untouched', function () {
    app(Laradocs::class)->share('product', 'Laradocs');

    $html = render("Use `{{ product }}` literally.\n\n```\n{{ product }}\n```");

    expect($html)->toContain('{{ product }}');
});

it('renders a registered macro with named arguments', function () {
    $html = render("@docs('alert', type: 'warning', body: 'Careful now')");

    expect($html)->toContain('laradocs-alert-warning')
        ->and($html)->toContain('Careful now');
});

it('converts github style callouts', function () {
    $html = render("> [!NOTE]\n> Remember this.");

    expect($html)->toContain('laradocs-callout laradocs-callout-note')
        ->and($html)->toContain('Remember this.');
});

it('adds ids and anchors to headings', function () {
    $html = render('## Getting Started');

    expect($html)->toContain('id="getting-started"')
        ->and($html)->toContain('laradocs-anchor');
});

it('wraps code blocks with a language label and copy button', function () {
    $html = render("```php\n echo 1;\n```");

    expect($html)->toContain('laradocs-code')
        ->and($html)->toContain('data-language="php"')
        ->and($html)->toContain('laradocs-code-copy');
});

it('enhances images with lazy loading and captions', function () {
    $html = render('![Alt text](/img/x.png "A caption")');

    expect($html)->toContain('loading="lazy"')
        ->and($html)->toContain('<figcaption>A caption</figcaption>');
});

it('converts local video and youtube links to players', function () {
    $video = render('![clip](/media/demo.mp4)');
    $youtube = render('[watch](https://youtu.be/abc123)');

    expect($video)->toContain('<video')
        ->and($video)->toContain('video/mp4')
        ->and($youtube)->toContain('youtube-nocookie.com/embed/abc123');
});

it('renders mermaid blocks as diagrams with a no-js fallback and lazy loader', function () {
    $html = render("```mermaid\ngraph TD; A-->B;\n```");

    expect($html)->toContain('class="laradocs-mermaid"')
        ->and($html)->toContain('laradocs-mermaid-source')
        // The graph source survives as the no-JS fallback.
        ->and($html)->toContain('graph TD; A--&gt;B;')
        // It is not dressed up as a copyable code block.
        ->and($html)->not->toContain('laradocs-code-copy')
        // The lazy loader is appended only because a diagram is present.
        ->and($html)->toContain('<script type="module">')
        ->and($html)->toContain('mermaid.esm.min.mjs');
});

it('only injects the mermaid loader once per page', function () {
    $html = render("```mermaid\ngraph TD; A-->B;\n```\n\n```mermaid\ngraph LR; C-->D;\n```");

    expect(substr_count($html, '<script type="module">'))->toBe(1)
        ->and(substr_count($html, 'class="laradocs-mermaid"'))->toBe(2);
});

it('leaves ordinary code blocks untouched by the mermaid extension', function () {
    $html = render("```php\necho 1;\n```");

    expect($html)->toContain('laradocs-code-copy')
        ->and($html)->not->toContain('laradocs-mermaid');
});

it('falls back to a plain code block when mermaid is disabled', function () {
    config()->set('laradocs.parser.extensions.mermaid', false);
    app()->forgetInstance(DocumentParser::class);

    $html = render("```mermaid\ngraph TD; A-->B;\n```");

    expect($html)->not->toContain('laradocs-mermaid')
        ->and($html)->not->toContain('mermaid.esm.min.mjs')
        // Without the extension it is just a normal fenced code block.
        ->and($html)->toContain('laradocs-code');
});

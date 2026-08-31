<?php

declare(strict_types=1);

use Laradocs\Laradocs;

/**
 * The rendered HTML of a page, by slug.
 */
function renderedHtml(string $slug): string
{
    return (string) app(Laradocs::class)->find($slug)?->html;
}

it('points a link to a sibling document at its slug', function () {
    $this->makeDocs([
        'guide/intro.md' => "---\ntitle: Intro\n---\n\nSee [Usage](usage.md).\n",
        'guide/usage.md' => "---\ntitle: Usage\n---\n\nBody.\n",
    ]);

    expect(renderedHtml('guide/intro'))
        ->toContain('href="' . route('laradocs.show', ['path' => 'guide/usage']) . '"')
        ->not->toContain('usage.md');
});

it('resolves a link that climbs out of its directory', function () {
    $this->makeDocs([
        'guide/advanced/deep.md' => "---\ntitle: Deep\n---\n\nBack to [Intro](../intro.md).\n",
        'guide/intro.md' => "---\ntitle: Intro\n---\n\nBody.\n",
    ]);

    expect(renderedHtml('guide/advanced/deep'))
        ->toContain('href="' . route('laradocs.show', ['path' => 'guide/intro']) . '"');
});

it('resolves a link written with an explicit current directory', function () {
    $this->makeDocs([
        'guide/intro.md' => "---\ntitle: Intro\n---\n\nSee [Usage](./usage.md).\n",
        'guide/usage.md' => "---\ntitle: Usage\n---\n\nBody.\n",
    ]);

    expect(renderedHtml('guide/intro'))
        ->toContain('href="' . route('laradocs.show', ['path' => 'guide/usage']) . '"');
});

it('resolves a link written from the docs root', function () {
    $this->makeDocs([
        'intro.md' => "---\ntitle: Intro\n---\n\nSee [Deep](guide/advanced/deep.md).\n",
        'guide/advanced/deep.md' => "---\ntitle: Deep\n---\n\nBody.\n",
    ]);

    expect(renderedHtml('intro'))
        ->toContain('href="' . route('laradocs.show', ['path' => 'guide/advanced/deep']) . '"');
});

/**
 * A section index is published under its directory, so a relative href on that
 * page would otherwise resolve a level too high in the browser. Resolving
 * against the file rather than the URL is the whole reason this runs where the
 * document is known.
 */
it('resolves links written on a section index against the file, not the url', function () {
    $this->makeDocs([
        'guide/_index.md' => "---\ntitle: Guide\n---\n\nStart at [Usage](usage.md).\n",
        'guide/usage.md' => "---\ntitle: Usage\n---\n\nBody.\n",
    ]);

    expect(renderedHtml('guide'))
        ->toContain('href="' . route('laradocs.show', ['path' => 'guide/usage']) . '"');
});

it('points a link to a section index at the section', function () {
    $this->makeDocs([
        'intro.md' => "---\ntitle: Intro\n---\n\nSee the [Guide](guide/_index.md).\n",
        'guide/_index.md' => "---\ntitle: Guide\n---\n\nBody.\n",
    ]);

    expect(renderedHtml('intro'))
        ->toContain('href="' . route('laradocs.show', ['path' => 'guide']) . '"');
});

it('keeps a fragment and a query on the resolved link', function () {
    $this->makeDocs([
        'intro.md' => "---\ntitle: Intro\n---\n\n[A](usage.md#tickets) and [B](usage.md?tab=cli).\n",
        'usage.md' => "---\ntitle: Usage\n---\n\nBody.\n",
    ]);

    $html = renderedHtml('intro');
    $usage = route('laradocs.show', ['path' => 'usage']);

    expect($html)
        ->toContain('href="' . $usage . '#tickets"')
        ->toContain('href="' . $usage . '?tab=cli"');
});

it('honours the configured document extensions', function () {
    config()->set('laradocs.docs.extensions', ['md', 'markdown']);

    $this->makeDocs([
        'intro.md' => "---\ntitle: Intro\n---\n\nSee [Usage](usage.markdown).\n",
        'usage.markdown' => "---\ntitle: Usage\n---\n\nBody.\n",
    ]);

    expect(renderedHtml('intro'))
        ->toContain('href="' . route('laradocs.show', ['path' => 'usage']) . '"');
});

it('leaves an extension that is not a document alone', function () {
    $this->makeDocs([
        'intro.md' => "---\ntitle: Intro\n---\n\nGrab the [handbook](handbook.pdf).\n",
    ]);

    expect(renderedHtml('intro'))->toContain('href="handbook.pdf"');
});

it('leaves links it does not own exactly as authored', function (string $href) {
    $this->makeDocs([
        'intro.md' => "---\ntitle: Intro\n---\n\nSee [Elsewhere](" . $href . ").\n",
    ]);

    expect(renderedHtml('intro'))->toContain('href="' . $href . '"');
})->with([
    'an absolute url' => 'https://example.com/readme.md',
    'a protocol-relative host' => '//example.com/readme.md',
    'a mail address' => 'mailto:hello@example.com',
    'a root-relative path' => '/downloads/readme.md',
    'a fragment on this page' => '#usage',
    'a link that is already a slug' => 'usage',
]);

it('refuses a link that climbs past the docs root', function () {
    $this->makeDocs([
        'intro.md' => "---\ntitle: Intro\n---\n\nSee [Outside](../../secrets.md).\n",
    ]);

    expect(renderedHtml('intro'))->toContain('href="../../secrets.md"');
});

/**
 * The slug a document is published under is not always its filename — the
 * resolver slugifies each segment — so the link has to go through the same
 * resolver rather than reusing the path.
 */
it('follows the slug resolver rather than the raw filename', function () {
    $this->makeDocs([
        'intro.md' => "---\ntitle: Intro\n---\n\nSee [Getting Started](Getting-Started.md).\n",
        'Getting-Started.md' => "---\ntitle: Getting Started\n---\n\nBody.\n",
    ]);

    expect(renderedHtml('intro'))
        ->toContain('href="' . route('laradocs.show', ['path' => 'getting-started']) . '"');
});

it('leaves a page without links untouched', function () {
    $this->makeDocs([
        'intro.md' => "---\ntitle: Intro\n---\n\nJust prose.\n",
    ]);

    expect(renderedHtml('intro'))->toContain('Just prose.');
});

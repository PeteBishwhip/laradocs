<?php

declare(strict_types=1);

use Laradocs\Documents\DocumentCollection;
use Laradocs\Documents\DocumentTree;
use Laradocs\Extensions\MacroExtension;
use Laradocs\Extensions\VariableExtension;
use Laradocs\Macros\MacroRegistry;
use Laradocs\Routing\LlmsFullTxtBuilder;
use Laradocs\Variables\VariableRegistry;

function llmsFullTree(): DocumentTree
{
    return DocumentTree::fromDocuments(new DocumentCollection([
        makeDocument('intro', ['title' => 'Intro'], "Body text.\n"),
    ]));
}

beforeEach(function () {
    config()->set('laradocs.versions.enabled', true);
    config()->set('laradocs.versions.strategy', 'config');
    config()->set('laradocs.versions.default', 'v2');
    config()->set('laradocs.versions.available', [
        'v2' => ['label' => 'v2.0'],
        'v1' => ['label' => 'v1.0'],
    ]);
});

it('excludes non-default version pages from llms-full.txt by default', function () {
    config()->set('laradocs._current_version', 'v1');

    $body = (new LlmsFullTxtBuilder)->build(llmsFullTree());

    expect($body)->not->toContain('## [');
});

it('includes the default version pages', function () {
    config()->set('laradocs._current_version', 'v2');

    expect((new LlmsFullTxtBuilder)->build(llmsFullTree()))->toContain('Body text.');
});

it('includes non-default versions when sitemap_all_versions is true', function () {
    config()->set('laradocs._current_version', 'v1');
    config()->set('laradocs.seo.sitemap_all_versions', true);

    expect((new LlmsFullTxtBuilder)->build(llmsFullTree()))->toContain('Body text.');
});

it('includes pages when versioning is disabled', function () {
    config()->set('laradocs.versions.enabled', false);
    config()->set('laradocs._current_version', 'v1');

    expect((new LlmsFullTxtBuilder)->build(llmsFullTree()))->toContain('Body text.');
});

it('still emits the header when every page is excluded', function () {
    config()->set('laradocs._current_version', 'v1');
    config()->set('laradocs.seo.site_name', 'Acme Docs');

    $body = (new LlmsFullTxtBuilder)->build(llmsFullTree());

    // Mirrors LlmsTxtBuilder's own behaviour: a valid file needs its H1 even
    // when the corpus below it is empty.
    expect($body)->toBe("# Acme Docs\n");
});

it('opens with the header LlmsTxtBuilder renders, not a reimplementation', function () {
    config()->set('laradocs.versions.enabled', false);
    config()->set('laradocs.seo.site_name', 'Acme Docs');
    config()->set('laradocs.seo.description', 'Everything about Acme.');

    $body = (new LlmsFullTxtBuilder)->build(llmsFullTree());

    expect($body)->toStartWith("# Acme Docs\n\n> Everything about Acme.\n");
});

it('introduces each page with a heading naming it and linking its canonical URL', function () {
    config()->set('laradocs.versions.enabled', false);

    $body = (new LlmsFullTxtBuilder)->build(llmsFullTree());

    expect($body)->toMatch('/## \[Intro\]\(\S+\)\n\nBody text\.\n/');
});

it('escapes square brackets in a title so the heading label survives', function () {
    config()->set('laradocs.versions.enabled', false);

    $tree = DocumentTree::fromDocuments(new DocumentCollection([
        makeDocument('a', ['title' => 'A [Draft] Page'], "body\n"),
    ]));

    expect((new LlmsFullTxtBuilder)->build($tree))->toContain('## [A \[Draft\] Page](');
});

it('excludes hidden documents', function () {
    config()->set('laradocs.versions.enabled', false);

    $tree = DocumentTree::fromDocuments(new DocumentCollection([
        makeDocument('visible', ['title' => 'Visible'], "Visible body.\n"),
        makeDocument('secret', ['title' => 'Secret', 'hidden' => true], "Shh.\n"),
    ]));

    $body = (new LlmsFullTxtBuilder)->build($tree);

    expect($body)->toContain('Visible body.')
        ->and($body)->not->toContain('Shh.');
});

it('excludes redirected documents', function () {
    config()->set('laradocs.versions.enabled', false);

    $tree = DocumentTree::fromDocuments(new DocumentCollection([
        makeDocument('keep', ['title' => 'Keep'], "Keep body.\n"),
        makeDocument('old', ['title' => 'Old', 'redirect' => 'keep'], "body\n"),
    ]));

    $body = (new LlmsFullTxtBuilder)->build($tree);

    expect($body)->toContain('Keep body.')
        ->and($body)->not->toContain('## [Old]');
});

it('runs variable interpolation over each page body when given a VariableExtension', function () {
    config()->set('laradocs.versions.enabled', false);

    $variables = new VariableRegistry(['product' => 'Acme']);
    $tree = DocumentTree::fromDocuments(new DocumentCollection([
        makeDocument('intro', ['title' => 'Intro'], "Welcome to {{ product }}.\n"),
    ]));

    $body = (new LlmsFullTxtBuilder([new VariableExtension($variables)]))->build($tree);

    expect($body)->toContain('Welcome to Acme.');
});

it('runs macro expansion over each page body when given a MacroExtension', function () {
    config()->set('laradocs.versions.enabled', false);

    $macros = new MacroRegistry(['shout' => fn (): string => 'LOUD']);
    $tree = DocumentTree::fromDocuments(new DocumentCollection([
        makeDocument('intro', ['title' => 'Intro'], "Say @docs('shout').\n"),
    ]));

    $body = (new LlmsFullTxtBuilder([new MacroExtension($macros)]))->build($tree);

    expect($body)->toContain('Say LOUD.');
});

it('emits raw markdown when no extensions are supplied', function () {
    config()->set('laradocs.versions.enabled', false);

    $tree = DocumentTree::fromDocuments(new DocumentCollection([
        makeDocument('intro', ['title' => 'Intro'], "Untouched {{ product }}.\n"),
    ]));

    $body = (new LlmsFullTxtBuilder)->build($tree);

    expect($body)->toContain('Untouched {{ product }}.');
});

it('reduces an openapi-backed page to a link-and-description stub', function () {
    config()->set('laradocs.versions.enabled', false);

    $tree = DocumentTree::fromDocuments(new DocumentCollection([
        makeDocument('api/pets', [
            'title' => 'List pets',
            'description' => 'List every pet in the store.',
            'openapi' => ['type' => 'operation'],
        ], ''),
    ]));

    $body = (new LlmsFullTxtBuilder)->build($tree);

    expect($body)->toContain('## [List pets](')
        ->and($body)->toContain('List every pet in the store.');
});

it('falls back to a generic stub when an openapi page declares no description', function () {
    config()->set('laradocs.versions.enabled', false);

    $tree = DocumentTree::fromDocuments(new DocumentCollection([
        makeDocument('api/pets', [
            'title' => 'List pets',
            'openapi' => ['type' => 'operation'],
        ], ''),
    ]));

    $body = (new LlmsFullTxtBuilder)->build($tree);

    expect($body)->toContain('See the linked page for the full API reference.');
});

it('truncates at a document boundary once full_max_bytes is exceeded', function () {
    config()->set('laradocs.versions.enabled', false);
    // Generous enough to comfortably fit one entry (content plus its heading
    // and URL overhead) but not two, so the cut falls cleanly between them.
    config()->set('laradocs.llms.full_max_bytes', 1200);

    $tree = DocumentTree::fromDocuments(new DocumentCollection([
        makeDocument('a', ['title' => 'A'], str_repeat('x', 1000) . "\n"),
        makeDocument('b', ['title' => 'B'], str_repeat('y', 1000) . "\n"),
    ]));

    $body = (new LlmsFullTxtBuilder)->build($tree);

    expect($body)->toContain(str_repeat('x', 1000))
        ->and($body)->not->toContain(str_repeat('y', 1000))
        ->and($body)->toContain('Truncated');
});

it('does not truncate when full_max_bytes is 0', function () {
    config()->set('laradocs.versions.enabled', false);
    config()->set('laradocs.llms.full_max_bytes', 0);

    $tree = DocumentTree::fromDocuments(new DocumentCollection([
        makeDocument('a', ['title' => 'A'], str_repeat('x', 100000) . "\n"),
    ]));

    $body = (new LlmsFullTxtBuilder)->build($tree);

    expect($body)->not->toContain('Truncated');
});

it('does not truncate when the corpus fits within full_max_bytes', function () {
    config()->set('laradocs.versions.enabled', false);
    config()->set('laradocs.llms.full_max_bytes', 1_000_000);

    $body = (new LlmsFullTxtBuilder)->build(llmsFullTree());

    expect($body)->not->toContain('Truncated');
});

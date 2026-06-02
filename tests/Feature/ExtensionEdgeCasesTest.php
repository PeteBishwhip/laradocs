<?php

declare(strict_types=1);

use Laradocs\Contracts\DocumentParser;

function parse(string $markdown): string
{
    return app(DocumentParser::class)->parse($markdown);
}

it('omits the language attribute for plain code blocks', function () {
    $html = parse("```\nplain\n```");

    expect($html)->toContain('laradocs-code')
        ->and($html)->not->toContain('data-language');
});

it('leaves unrecognised callout types as blockquotes', function () {
    $html = parse('> [!FOO]\n> Not a callout.');

    expect($html)->toContain('<blockquote')
        ->and($html)->not->toContain('laradocs-callout');
});

it('does not wrap an image without a caption in a figure', function () {
    $html = parse('![alt only](/img.png)');

    expect($html)->toContain('laradocs-image')
        ->and($html)->not->toContain('<figure');
});

it('drops unknown macros to nothing', function () {
    expect(trim(parse("@docs('ghost', x: 1)")))->not->toContain('ghost');
});

it('keeps unknown variables verbatim in raw mode', function () {
    config()->set('laradocs.parser.unknown_variable', 'raw');
    app()->forgetInstance(DocumentParser::class);

    expect(parse('Value: {{ unknown_thing }}'))->toContain('{{ unknown_thing }}');
});

it('renders standard formatting: emphasis, quotes, lists and links', function () {
    $html = parse("**bold** _italic_ ~~strike~~\n\n> quoted\n\n- one\n- two\n\n[link](https://example.com)");

    expect($html)->toContain('<strong>bold</strong>')
        ->and($html)->toContain('<em>italic</em>')
        ->and($html)->toContain('<del>strike</del>')
        ->and($html)->toContain('<blockquote>')
        ->and($html)->toContain('<li>one</li>')
        ->and($html)->toContain('href="https://example.com"');
});

it('renders tables via the gfm extension', function () {
    $html = parse("| A | B |\n|---|---|\n| 1 | 2 |");

    expect($html)->toContain('<table>')->toContain('<td>1</td>');
});

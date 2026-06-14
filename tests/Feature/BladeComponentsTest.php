<?php

declare(strict_types=1);

use Laradocs\Contracts\DocumentParser;
use Laradocs\Laradocs;

function component(string $markdown): string
{
    return app(DocumentParser::class)->parse($markdown);
}

it('renders a whitelisted self-closing component', function () {
    $html = component('<x-badge text="Beta" />');

    expect($html)->toContain('laradocs-pill')
        ->and($html)->toContain('Beta');
});

it('renders a paired component, passing the inner content as the slot', function () {
    $html = component('<x-callout type="warning" title="Heads up">Back up first.</x-callout>');

    expect($html)->toContain('laradocs-callout-warning')
        ->and($html)->toContain('Heads up')
        ->and($html)->toContain('Back up first.');
});

it('renders a block component whose tags span multiple lines', function () {
    $html = component("<x-callout type=\"tip\">\nLine one.\nLine two.\n</x-callout>");

    expect($html)->toContain('laradocs-callout-tip')
        ->and($html)->toContain('Line one.')
        ->and($html)->toContain('Line two.');
});

it('round-trips with the macro engine for equivalent calls', function () {
    app(Laradocs::class)->macro('note', fn (array $arguments): string => sprintf(
        '<aside data-tone="%s">%s</aside>',
        $arguments['tone'] ?? 'plain',
        $arguments['slot'] ?? '',
    ));

    $viaComponent = component('<x-note tone="warm">Hello there</x-note>');
    $viaMacro = component("@docs('note', tone: 'warm', slot: 'Hello there')");

    expect($viaComponent)->toBe($viaMacro)
        ->and($viaComponent)->toContain('data-tone="warm"')
        ->and($viaComponent)->toContain('Hello there');
});

it('casts attribute values the same way the macro engine does', function () {
    app(Laradocs::class)->macro('probe', fn (array $arguments): string => sprintf(
        '<i>%s|%s|%s</i>',
        var_export($arguments['open'] ?? null, true),
        var_export($arguments['count'] ?? null, true),
        var_export($arguments['flag'] ?? null, true),
    ));

    // Bare scalars cast; a bound `:count` unwraps then casts; a valueless
    // attribute becomes boolean true.
    $html = component('<x-probe open="true" :count="3" flag />');

    expect($html)->toContain('<i>\'true\'|3|true</i>');
});

it('leaves unknown components untouched', function () {
    $html = component('<x-not-registered foo="bar">body</x-not-registered>');

    expect($html)->toContain('x-not-registered')
        ->and($html)->toContain('body');
});

it('renders a backslash-escaped component as literal text', function () {
    $html = component('Type \<x-callout> to add a callout.');

    expect($html)->toContain('&lt;x-callout&gt;')
        ->and($html)->not->toContain('laradocs-callout');
});

it('leaves components inside code spans and fenced blocks untouched', function () {
    $html = component("Use `<x-badge text=\"x\" />` inline.\n\n```\n<x-badge text=\"x\" />\n```");

    expect($html)->toContain('&lt;x-badge')
        ->and($html)->not->toContain('laradocs-pill');
});

it('expands components nested inside a slot', function () {
    app(Laradocs::class)->macro('wrap', fn (array $arguments): string => '<div class="wrap">' . ($arguments['slot'] ?? '') . '</div>');

    $html = component('<x-wrap>Get <x-badge text="Beta" /> today</x-wrap>');

    expect($html)->toContain('class="wrap"')
        ->and($html)->toContain('laradocs-pill')
        ->and($html)->toContain('Beta');
});

it('treats an opening tag with no matching close as literal text', function () {
    $html = component('<x-callout>dangling with no close');

    expect($html)->not->toContain('laradocs-callout')
        ->and($html)->toContain('dangling with no close');
});

it('ignores a malformed component-like fragment', function () {
    expect(fn () => component('before <x-> after'))->not->toThrow(Exception::class);
});

it('leaves a tag inside an unterminated fence untouched', function () {
    $html = component("```\n<x-badge text=\"x\" />");

    expect($html)->not->toContain('laradocs-pill');
});

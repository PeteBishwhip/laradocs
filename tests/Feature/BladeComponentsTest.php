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
    app(Laradocs::class)->macro('note', function (array $arguments): string {
        return sprintf(
            '<aside data-tone="%s">%s</aside>',
            $arguments['tone'] ?? 'plain',
            $arguments['slot'] ?? '',
        );
    });

    $viaComponent = component('<x-note tone="warm">Hello there</x-note>');
    $viaMacro = component("@docs('note', tone: 'warm', slot: 'Hello there')");

    expect($viaComponent)->toBe($viaMacro)
        ->and($viaComponent)->toContain('data-tone="warm"')
        ->and($viaComponent)->toContain('Hello there');
});

it('casts attribute values the same way the macro engine does', function () {
    app(Laradocs::class)->macro('probe', function (array $arguments): string {
        return sprintf(
            '<i>%s|%s|%s</i>',
            var_export($arguments['open'] ?? null, true),
            var_export($arguments['count'] ?? null, true),
            var_export($arguments['flag'] ?? null, true),
        );
    });

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
    app(Laradocs::class)->macro('wrap', function (array $arguments): string {
        return '<div class="wrap">' . ($arguments['slot'] ?? '') . '</div>';
    });

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
    expect(component('before <x-> after'))->toContain('before')
        ->and(component('before <x-> after'))->toContain('after');
});

it('leaves a tag inside an unterminated fence untouched', function () {
    $html = component("```\n<x-badge text=\"x\" />");

    expect($html)->not->toContain('laradocs-pill');
});

it('treats an opening tag with no closing > as literal text', function () {
    // The needle "<x-" is found but never reaches a closing bracket, so the
    // parser bails out and leaves the source alone (no macro fires).
    $html = component('Hint: <x-badge text="x" left dangling at end-of-input');

    expect($html)->not->toContain('laradocs-pill');
});

it('treats a tag whose name runs straight up to end-of-input as literal', function () {
    // Name characters extend to the final byte — the boundary check has to
    // accept "past end of string" as a boundary so the parser can fall through
    // to the no-`>` bail-out instead of treating the EOF as an attribute char.
    $html = component('A trailing tag: <x-badge');

    expect($html)->not->toContain('laradocs-pill');
});

it('tracks depth when the same component nests inside itself', function () {
    $html = component('<x-callout type="tip">outer <x-callout type="note">inner</x-callout> tail</x-callout>');

    // Depth tracking has to walk *past* the inner close to find the outer one;
    // both callouts should render side-by-side rather than the outer being
    // truncated at the first </x-callout>.
    expect($html)->toContain('laradocs-callout-tip')
        ->and($html)->toContain('laradocs-callout-note')
        ->and($html)->toContain('inner')
        ->and($html)->toContain('tail');
});

it('does not bump depth when the nested same-name tag is self-closing', function () {
    app(Laradocs::class)->macro('selfish', function (array $arguments): string {
        return '<i class="selfish-' . ($arguments['k'] ?? '') . '">' . ($arguments['slot'] ?? '') . '</i>';
    });

    $html = component('<x-selfish k="outer">A <x-selfish k="inner" /> B</x-selfish>');

    expect($html)->toContain('class="selfish-outer"')
        ->and($html)->toContain('class="selfish-inner"');
});

it('skips a prefix-collision tag while scanning for the matching close', function () {
    // `<x-foo` is a prefix of `<x-foobar`. The closing-tag scan must NOT mistake
    // the nested longer-named tag for another opening of the outer component.
    app(Laradocs::class)->macro('foo', function (array $arguments): string {
        return '<div class="x-foo">' . ($arguments['slot'] ?? '') . '</div>';
    });
    app(Laradocs::class)->macro('foobar', function (): string {
        return '<span class="x-foobar"></span>';
    });

    $html = component('<x-foo>start <x-foobar /> end</x-foo>');

    expect($html)->toContain('class="x-foo"')
        ->and($html)->toContain('class="x-foobar"')
        ->and($html)->toContain('start')
        ->and($html)->toContain('end');
});

it('treats an unbalanced same-name nest as literal when the outer never closes', function () {
    // Inner pair is balanced (`<x-callout>...</x-callout>`) but the outer one
    // is dangling — depth-tracking runs to the end of input without ever
    // returning to zero, so the outer opening tag must be emitted verbatim
    // while the inner pair still renders.
    $html = component('<x-callout type="tip">outer-text<x-callout type="note">inner-text</x-callout>');

    expect($html)->toContain('laradocs-callout-note')
        ->and($html)->toContain('inner-text')
        ->and($html)->not->toContain('laradocs-callout-tip');
});

it('still expands a component on a line with an unbalanced inline backtick', function () {
    // A lone backtick has no closing partner on the line, so the masker leaves
    // the whole line alone — the component should still render.
    $html = component('say `loose <x-badge text="Beta" /> rest');

    expect($html)->toContain('laradocs-pill')
        ->and($html)->toContain('Beta');
});

it('reads bare, unquoted attribute values and casts them', function () {
    app(Laradocs::class)->macro('vals', function (array $arguments): string {
        return sprintf(
            '<b>%s|%s|%s|%s</b>',
            var_export($arguments['flag'] ?? null, true),
            var_export($arguments['off'] ?? null, true),
            var_export($arguments['n'] ?? null, true),
            var_export($arguments['s'] ?? null, true),
        );
    });

    // No quotes around any value — exercises the unquoted-value reader.
    $html = component('<x-vals flag=true off=false n=42 s=plain />');

    expect($html)->toContain("<b>true|false|42|'plain'</b>");
});

it('skips stray characters between attributes', function () {
    // The leading `!` is not a valid attribute-name start, so the parser skips
    // it and carries on reading the real `text` attribute.
    $html = component('<x-badge ! text="Beta" />');

    expect($html)->toContain('laradocs-pill')
        ->and($html)->toContain('Beta');
});

it('treats an attribute with a trailing = and no value as valueless', function () {
    // `text=` runs out before a value — it falls back to a valueless (true)
    // attribute rather than erroring.
    expect(function () {
        return component('<x-badge text= />');
    })->not->toThrow(Exception::class);

    expect(component('<x-badge text= />'))->toContain('laradocs-pill');
});

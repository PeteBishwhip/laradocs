<?php

declare(strict_types=1);

it('derives a title from the filename when metadata omits one', function () {
    expect(makeDocument('guide/getting-started')->title())->toBe('Getting Started');
});

it('prefers the metadata title', function () {
    expect(makeDocument('guide/intro', ['title' => 'Introduction'])->title())->toBe('Introduction');
});

it('exposes metadata helpers', function () {
    $doc = makeDocument('x', ['hidden' => true, 'order' => 4, 'group' => 'Basics', 'redirect' => 'y']);

    expect($doc->isHidden())->toBeTrue()
        ->and($doc->order())->toBe(4)
        ->and($doc->group())->toBe('Basics')
        ->and($doc->redirect())->toBe('y');
});

it('splits the slug into segments and reports depth', function () {
    expect(makeDocument('a/b/c')->segments())->toBe(['a', 'b', 'c'])
        ->and(makeDocument('a/b/c')->depth())->toBe(3)
        ->and(makeDocument('')->segments())->toBe([])
        ->and(makeDocument('')->depth())->toBe(0);
});

it('attaches rendered html immutably', function () {
    $doc = makeDocument('x');
    $rendered = $doc->withHtml('<p>hi</p>');

    expect($doc->html)->toBeNull()
        ->and($rendered->html)->toBe('<p>hi</p>')
        ->and($rendered->slug)->toBe('x');
});

it('serialises to an array', function () {
    $array = makeDocument('x', ['title' => 'X'])->withHtml('<p>y</p>')->toArray();

    expect($array['slug'])->toBe('x')
        ->and($array['title'])->toBe('X')
        ->and($array['html'])->toBe('<p>y</p>');
});

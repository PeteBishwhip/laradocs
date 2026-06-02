<?php

declare(strict_types=1);

use Laradocs\Toc\TableOfContents;

it('collects headings within the configured levels', function () {
    $html = '<h1 id="t">Title</h1><h2 id="a">Alpha</h2><h3 id="b">Beta</h3><h4 id="c">Gamma</h4>';

    $toc = TableOfContents::fromHtml($html, 2, 3);

    expect($toc->count())->toBe(2)
        ->and($toc->headings[0]->id)->toBe('a')
        ->and($toc->headings[0]->level)->toBe(2)
        ->and($toc->headings[1]->text)->toBe('Beta');
});

it('ignores headings without ids', function () {
    $toc = TableOfContents::fromHtml('<h2>No id</h2><h2 id="y">Yes</h2>', 2, 3);

    expect($toc->count())->toBe(1)
        ->and($toc->headings[0]->id)->toBe('y');
});

it('is empty for blank html', function () {
    expect(TableOfContents::fromHtml('   ')->isEmpty())->toBeTrue();
});

<?php

declare(strict_types=1);

use Laradocs\Laradocs;

beforeEach(function () {
    config()->set('laradocs.docs.path', dirname(__DIR__, 2) . '/docs');
});

it('loads the package\'s own documentation', function () {
    expect(app(Laradocs::class)->all()->count())->toBeGreaterThan(5);
});

it('renders every page without error', function () {
    app(Laradocs::class)->all()->each(function ($document) {
        $html = app(Laradocs::class)->render($document);
        expect($html)->toBeString()->not->toBe('');
    });
});

it('has no broken internal documentation links', function () {
    $laradocs = app(Laradocs::class);
    $slugs = $laradocs->all()->pluck('slug')->push('')->all();

    $broken = [];

    foreach ($laradocs->all() as $document) {
        $html = $laradocs->render($document);
        preg_match_all('~href="/docs/([^"#]*)~', $html, $matches);

        foreach ($matches[1] as $target) {
            if (! in_array(trim($target, '/'), array_map(fn ($s) => trim($s, '/'), $slugs), true)) {
                $broken[] = $document->slug . ' -> /docs/' . $target;
            }
        }
    }

    expect($broken)->toBe([]);
});

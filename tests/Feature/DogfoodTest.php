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
    // Redirect stubs (e.g. the old paths kept for backwards compatibility)
    // carry only front-matter and no body — the controller 301s them before
    // rendering, so there is nothing to render. Skip them here.
    app(Laradocs::class)->all()
        ->reject(function ($document) {
            return $document->redirect() !== null;
        })
        ->each(function ($document) {
            $html = app(Laradocs::class)->render($document);
            expect($html)->toBeString()->not->toBe('');
        });
});

it('has no broken internal documentation links', function () {
    $laradocs = app(Laradocs::class);
    $slugs = $laradocs->all()->pluck('slug')->push('')->all();

    $broken = [];

    foreach ($laradocs->all() as $document) {
        $html = preg_replace('~<pre\b[^>]*>.*?</pre>~s', '', $laradocs->render($document)) ?? '';
        preg_match_all('~href="/docs/([^"#]*)~', $html, $matches);

        foreach ($matches[1] as $target) {
            if (! in_array(trim($target, '/'), array_map(function ($s) {
                return trim($s, '/');
            }, $slugs), true)) {
                $broken[] = $document->slug . ' -> /docs/' . $target;
            }
        }
    }

    expect($broken)->toBe([]);
});

<?php

declare(strict_types=1);

use Laradocs\Icons\IconRegistry;

it('renders an icon from a registered set', function () {
    $registry = new IconRegistry('my-set');
    $registry->register('my-set', fn (string $name, string $variant): string => "<svg>{$name}-{$variant}</svg>");

    $html = $registry->render('arrow-right');

    expect($html)
        ->toContain('laradocs-icon')
        ->toContain('<svg>arrow-right-outline</svg>');
});

it('renders an icon from an explicitly named set', function () {
    $registry = new IconRegistry('default');
    $registry->register('custom', fn (string $name, string $variant): string => "<svg>{$name}</svg>");

    expect($registry->render('star', 'outline', 'custom'))->toContain('<svg>star</svg>');
});

it('passes the variant to the provider', function () {
    $received = [];
    $registry = new IconRegistry('icons');
    $registry->register('icons', function (string $name, string $variant) use (&$received): string {
        $received = [$name, $variant];

        return '<svg/>';
    });

    $registry->render('check', 'solid');

    expect($received)->toBe(['check', 'solid']);
});

it('returns empty string when the set is not registered', function () {
    $registry = new IconRegistry('missing');

    expect($registry->render('arrow'))->toBe('');
});

it('returns empty string for an empty icon name', function () {
    $registry = new IconRegistry('icons');
    $registry->register('icons', fn (): string => '<svg/>');

    expect($registry->render(''))->toBe('');
});

it('returns empty string when the provider returns empty', function () {
    $registry = new IconRegistry('icons');
    $registry->register('icons', fn (): string => '');

    expect($registry->render('not-found'))->toBe('');
});

it('wraps SVG in a span with aria-hidden', function () {
    $registry = new IconRegistry('icons');
    $registry->register('icons', fn (): string => '<svg></svg>');

    $html = $registry->render('check');

    expect($html)->toBe('<span class="laradocs-icon" aria-hidden="true"><svg></svg></span>');
});

it('reports whether a set is registered', function () {
    $registry = new IconRegistry('icons');
    $registry->register('heroicons', fn (): string => '');

    expect($registry->has('heroicons'))->toBeTrue()
        ->and($registry->has('phosphor'))->toBeFalse();
});

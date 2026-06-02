<?php

declare(strict_types=1);

use Laradocs\Variables\VariableRegistry;

it('stores and reads scalar values', function () {
    $registry = (new VariableRegistry)->set('name', 'Acme');

    expect($registry->get('name'))->toBe('Acme')
        ->and($registry->has('name'))->toBeTrue()
        ->and($registry->has('missing'))->toBeFalse()
        ->and($registry->get('missing', 'd'))->toBe('d');
});

it('seeds from constructor values', function () {
    expect((new VariableRegistry(['a' => 1]))->get('a'))->toBe(1);
});

it('resolves closure values lazily', function () {
    $registry = (new VariableRegistry)->set('time', fn (): string => 'computed');

    expect($registry->get('time'))->toBe('computed');
});

it('supports dotted access into nested arrays', function () {
    $registry = (new VariableRegistry)->set('app', ['name' => 'Docs']);

    expect($registry->get('app.name'))->toBe('Docs')
        ->and($registry->has('app.name'))->toBeTrue();
});

it('merges arrays and defers closures via register()', function () {
    $registry = (new VariableRegistry)
        ->register(['a' => 1])
        ->register(fn (): array => ['b' => 2]);

    expect($registry->all())->toMatchArray(['a' => 1, 'b' => 2]);
});

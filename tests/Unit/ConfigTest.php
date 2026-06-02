<?php

declare(strict_types=1);

use Laradocs\Support\Config;

it('reads typed scalars with fallbacks', function () {
    config()->set('laradocs.test', [
        'str' => 'value',
        'num' => '42',
        'flag' => 1,
        'arr' => ['a'],
        'obj' => new stdClass,
    ]);

    expect(Config::string('laradocs.test.str'))->toBe('value')
        ->and(Config::string('laradocs.test.missing', 'def'))->toBe('def')
        ->and(Config::string('laradocs.test.obj', 'def'))->toBe('def')
        ->and(Config::int('laradocs.test.num'))->toBe(42)
        ->and(Config::int('laradocs.test.str', 7))->toBe(7)
        ->and(Config::bool('laradocs.test.flag'))->toBeTrue()
        ->and(Config::array('laradocs.test.arr'))->toBe(['a'])
        ->and(Config::array('laradocs.test.str', ['fallback']))->toBe(['fallback']);
});

it('reads nullable values', function () {
    config()->set('laradocs.test', ['num' => '5']);

    expect(Config::nullableString('laradocs.test.num'))->toBe('5')
        ->and(Config::nullableString('laradocs.test.none'))->toBeNull()
        ->and(Config::nullableInt('laradocs.test.num'))->toBe(5)
        ->and(Config::nullableInt('laradocs.test.none'))->toBeNull();
});

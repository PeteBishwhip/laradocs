<?php

declare(strict_types=1);

use Laradocs\OpenApi\Coerce;

it('narrows scalars and falls back for unexpected types', function () {
    expect(Coerce::string(42))->toBe('42')
        ->and(Coerce::string(['x']))->toBe('')
        ->and(Coerce::nullableString(1.5))->toBe('1.5')
        ->and(Coerce::nullableString(['x']))->toBeNull()
        ->and(Coerce::bool(1))->toBeTrue()
        ->and(Coerce::bool(['x']))->toBeFalse();
});

it('returns empty arrays when array helpers receive a non-array', function () {
    expect(Coerce::assoc('nope'))->toBe([])
        ->and(Coerce::listOfAssoc('nope'))->toBe([])
        ->and(Coerce::stringList('nope'))->toBe([]);
});

it('coerces nested structures element-by-element', function () {
    expect(Coerce::listOfAssoc([['a' => 1], 'skip', ['b' => 2]]))
        ->toBe([['a' => 1], [], ['b' => 2]])
        ->and(Coerce::stringList(['a', 2, ['nested'], 3.0]))
        ->toBe(['a', '2', '3']);
});

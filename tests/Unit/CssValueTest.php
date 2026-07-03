<?php

declare(strict_types=1);

use Laradocs\Support\CssValue;

it('allows ordinary CSS custom property values', function () {
    expect(CssValue::customProperty('#ff2d20'))->toBe('#ff2d20')
        ->and(CssValue::customProperty('72rem'))->toBe('72rem')
        ->and(CssValue::customProperty('Inter, system-ui, sans-serif'))->toBe('Inter, system-ui, sans-serif')
        ->and(CssValue::customProperty('rgb(255 45 32)'))->toBe('rgb(255 45 32)');
});

it('rejects CSS and HTML break-out characters', function () {
    expect(CssValue::customProperty('red; body { display: none }'))->toBeNull()
        ->and(CssValue::customProperty('red}</style><script>alert(1)</script>'))->toBeNull()
        ->and(CssValue::customProperty("red\nblue"))->toBeNull()
        ->and(CssValue::customProperty('red /* hidden */'))->toBeNull()
        ->and(CssValue::customProperty(''))->toBeNull()
        ->and(CssValue::customProperty(['red']))->toBeNull();
});

<?php

declare(strict_types=1);

use Laradocs\Icons\IconReference;

it('parses a bare positional name', function () {
    $reference = IconReference::parse("'arrow-right'");

    expect($reference->name)->toBe('arrow-right');
    expect($reference->variant)->toBe('outline');
    expect($reference->set)->toBeNull();
});

it('returns an empty reference for an empty argument string', function () {
    $reference = IconReference::parse('   ');

    expect($reference->name)->toBe('');
    expect($reference->variant)->toBe('outline');
    expect($reference->set)->toBeNull();
});

it('honours a custom default variant', function () {
    $reference = IconReference::parse("'check'", 'solid');

    expect($reference->variant)->toBe('solid');
});

it('parses variant and set named arguments', function () {
    $reference = IconReference::parse("'check', variant: 'mini', set: 'heroicons'");

    expect($reference->name)->toBe('check');
    expect($reference->variant)->toBe('mini');
    expect($reference->set)->toBe('heroicons');
});

it('ignores tokens that are not named arguments', function () {
    $reference = IconReference::parse("'check', 'extra', variant: 'solid'");

    expect($reference->name)->toBe('check');
    expect($reference->variant)->toBe('solid');
    expect($reference->set)->toBeNull();
});

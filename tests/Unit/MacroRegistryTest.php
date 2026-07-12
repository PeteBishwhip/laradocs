<?php

declare(strict_types=1);

use Laradocs\Exceptions\UnknownMacroException;
use Laradocs\Macros\MacroRegistry;

it('registers and reports macros', function () {
    $registry = (new MacroRegistry)->register('hi', function (): string {
        return 'hello';
    });

    expect($registry->has('hi'))->toBeTrue()
        ->and($registry->names())->toBe(['hi']);
});

it('renders a closure macro with arguments', function () {
    $registry = (new MacroRegistry)->register(
        'greet',
        function (array $arguments): string {
            return 'Hi ' . ($arguments['name'] ?? 'there');
        }
    );

    expect($registry->render('greet', ['name' => 'Sam']))->toBe('Hi Sam');
});

it('renders a blade view macro', function () {
    $html = (new MacroRegistry)
        ->register('alert', 'laradocs::macros.alert')
        ->render('alert', ['type' => 'info', 'body' => 'Heads up']);

    expect($html)->toContain('laradocs-alert-info')
        ->and($html)->toContain('Heads up');
});

it('throws for unknown macros', function () {
    (new MacroRegistry)->render('nope');
})->throws(UnknownMacroException::class);

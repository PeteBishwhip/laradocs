<?php

declare(strict_types=1);

use Laradocs\Support\CodeAwareReplacer;

$upper = fn (string $text): string => strtoupper($text);

it('transforms normal text', function () use ($upper) {
    expect(CodeAwareReplacer::apply('hello world', $upper))->toBe('HELLO WORLD');
});

it('skips fenced code blocks', function () use ($upper) {
    $input = "before\n```\nkeep me\n```\nafter";

    expect(CodeAwareReplacer::apply($input, $upper))
        ->toBe("BEFORE\n```\nkeep me\n```\nAFTER");
});

it('skips inline code spans', function () use ($upper) {
    expect(CodeAwareReplacer::apply('say `hello` now', $upper))->toBe('SAY `hello` NOW');
});

it('handles tilde fences', function () use ($upper) {
    $input = "a\n~~~\ncode\n~~~\nb";

    expect(CodeAwareReplacer::apply($input, $upper))->toBe("A\n~~~\ncode\n~~~\nB");
});

it('keeps a placeholder restorable after the caller trims it', function () {
    [$masked, $restore] = CodeAwareReplacer::protect("```php\necho 1;\n```");

    expect($restore(trim($masked)))->toBe("```php\necho 1;\n```");
});

it('leaves a block unmasked when the except predicate accepts its opener', function () {
    $input = "````\n```php tab:PHP\necho 1;\n```\n````";

    [$masked] = CodeAwareReplacer::protect(
        $input,
        static fn (string $opener): bool => str_contains($opener, 'tab:'),
    );

    // The outer fence is masked whole, so the tab-tagged fence nested in it is
    // never offered to the predicate in the first place.
    expect($masked)->not->toContain('tab:PHP');

    [$masked] = CodeAwareReplacer::protect(
        "```php tab:PHP\necho 1;\n```",
        static fn (string $opener): bool => str_contains($opener, 'tab:'),
    );

    expect($masked)->toContain('tab:PHP');
});

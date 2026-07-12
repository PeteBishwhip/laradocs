<?php

declare(strict_types=1);

use Laradocs\Support\CodeAwareReplacer;

$upper = function (string $text): string {
    return strtoupper($text);
};

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

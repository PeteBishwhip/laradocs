<?php

declare(strict_types=1);

use Laradocs\Metadata\FrontMatterMetadataResolver;

beforeEach(function () {
    $this->resolver = new FrontMatterMetadataResolver;
});

it('splits front-matter from the body', function () {
    [$matter, $body] = $this->resolver->resolve("---\ntitle: Hi\n---\n# Body\n");

    expect($matter)->toBe(['title' => 'Hi'])
        ->and(trim($body))->toBe('# Body');
});

it('returns an empty matter array when there is no front-matter', function () {
    [$matter, $body] = $this->resolver->resolve("# Just markdown\n");

    expect($matter)->toBe([])
        ->and(trim($body))->toBe('# Just markdown');
});

it('treats malformed front-matter as plain body', function () {
    $raw = "---\n: : : broken\n  bad\n---\n# Body\n";

    [$matter, $body] = $this->resolver->resolve($raw);

    expect($matter)->toBeArray()
        ->and($body)->toContain('Body');
});

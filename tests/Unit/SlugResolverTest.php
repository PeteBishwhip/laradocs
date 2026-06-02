<?php

declare(strict_types=1);

use Laradocs\Routing\SlugResolver;

it('derives slugs from filenames', function () {
    $resolver = new SlugResolver('filename');

    expect($resolver->resolve('guide/Getting Started.md'))->toBe('guide/getting-started')
        ->and($resolver->resolve('intro.md'))->toBe('intro');
});

it('treats index files as their parent directory', function () {
    $resolver = new SlugResolver('filename', '_index');

    expect($resolver->resolve('guide/_index.md'))->toBe('guide')
        ->and($resolver->resolve('_index.md'))->toBe('');
});

it('prefers metadata slug when strategy allows', function () {
    $resolver = new SlugResolver('both');

    expect($resolver->resolve('guide/intro.md', 'custom/path'))->toBe('custom/path');
});

it('ignores metadata slug under the filename strategy', function () {
    $resolver = new SlugResolver('filename');

    expect($resolver->resolve('guide/intro.md', 'custom'))->toBe('guide/intro');
});

it('falls back to the filename when metadata slug is empty', function () {
    $resolver = new SlugResolver('metadata');

    expect($resolver->resolve('guide/intro.md', null))->toBe('guide/intro')
        ->and($resolver->resolve('guide/intro.md', ''))->toBe('guide/intro');
});

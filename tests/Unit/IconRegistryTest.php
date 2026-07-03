<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Laradocs\Icons\HeroiconProvider;
use Laradocs\Icons\IconRegistry;

// ---------------------------------------------------------------------------
// IconRegistry
// ---------------------------------------------------------------------------

it('renders an icon from a registered set', function () {
    $registry = new IconRegistry('my-set');
    $registry->register('my-set', fn (string $name, string $variant): string => "<svg>{$name}-{$variant}</svg>");

    $html = $registry->render('arrow-right');

    expect($html)
        ->toContain('laradocs-icon')
        ->toContain('<svg>arrow-right-outline</svg>');
});

it('renders an icon from an explicitly named set', function () {
    $registry = new IconRegistry('default');
    $registry->register('custom', fn (string $name, string $variant): string => "<svg>{$name}</svg>");

    expect($registry->render('star', 'outline', 'custom'))->toContain('<svg>star</svg>');
});

it('passes the variant to the provider', function () {
    $received = [];
    $registry = new IconRegistry('icons');
    $registry->register('icons', function (string $name, string $variant) use (&$received): string {
        $received = [$name, $variant];

        return '<svg/>';
    });

    $registry->render('check', 'solid');

    expect($received)->toBe(['check', 'solid']);
});

it('returns empty string when the set is not registered', function () {
    $registry = new IconRegistry('missing');

    expect($registry->render('arrow'))->toBe('');
});

it('returns empty string for an empty icon name', function () {
    $registry = new IconRegistry('icons');
    $registry->register('icons', fn (): string => '<svg/>');

    expect($registry->render(''))->toBe('');
});

it('returns empty string when the provider returns empty', function () {
    $registry = new IconRegistry('icons');
    $registry->register('icons', fn (): string => '');

    expect($registry->render('not-found'))->toBe('');
});

it('wraps SVG in a span with aria-hidden', function () {
    $registry = new IconRegistry('icons');
    $registry->register('icons', fn (): string => '<svg></svg>');

    $html = $registry->render('check');

    expect($html)->toBe('<span class="laradocs-icon" aria-hidden="true"><svg></svg></span>');
});

it('reports whether a set is registered', function () {
    $registry = new IconRegistry('icons');
    $registry->register('heroicons', fn (): string => '');

    expect($registry->has('heroicons'))->toBeTrue()
        ->and($registry->has('phosphor'))->toBeFalse();
});

it('returns the default set name', function () {
    $registry = new IconRegistry('heroicons');

    expect($registry->getDefaultSet())->toBe('heroicons');
});

// ---------------------------------------------------------------------------
// HeroiconProvider
// ---------------------------------------------------------------------------

it('reads an SVG file from the heroicons directory', function () {
    $root = sys_get_temp_dir() . '/laradocs-heroicons-' . bin2hex(random_bytes(4));
    $files = new Filesystem;
    $files->ensureDirectoryExists($root . '/24/outline');
    $files->put($root . '/24/outline/arrow-right.svg', '<svg><path d="M1"/></svg>');

    $provider = new HeroiconProvider($root, $files);

    expect($provider('arrow-right'))->toContain('<svg>');

    $files->deleteDirectory($root);
});

it('reads the solid variant', function () {
    $root = sys_get_temp_dir() . '/laradocs-heroicons-' . bin2hex(random_bytes(4));
    $files = new Filesystem;
    $files->ensureDirectoryExists($root . '/24/solid');
    $files->put($root . '/24/solid/check.svg', '<svg class="solid"></svg>');

    $provider = new HeroiconProvider($root, $files);

    expect($provider('check', 'solid'))->toContain('class="solid"');

    $files->deleteDirectory($root);
});

it('reads the mini variant from the 20px directory', function () {
    $root = sys_get_temp_dir() . '/laradocs-heroicons-' . bin2hex(random_bytes(4));
    $files = new Filesystem;
    $files->ensureDirectoryExists($root . '/20/mini');
    $files->put($root . '/20/mini/star.svg', '<svg class="mini"></svg>');

    $provider = new HeroiconProvider($root, $files);

    expect($provider('star', 'mini'))->toContain('class="mini"');

    $files->deleteDirectory($root);
});

it('reads the micro variant from the 16px directory', function () {
    $root = sys_get_temp_dir() . '/laradocs-heroicons-' . bin2hex(random_bytes(4));
    $files = new Filesystem;
    $files->ensureDirectoryExists($root . '/16/micro');
    $files->put($root . '/16/micro/x-mark.svg', '<svg class="micro"></svg>');

    $provider = new HeroiconProvider($root, $files);

    expect($provider('x-mark', 'micro'))->toContain('class="micro"');

    $files->deleteDirectory($root);
});

it('falls back to outline for an unknown variant', function () {
    $root = sys_get_temp_dir() . '/laradocs-heroicons-' . bin2hex(random_bytes(4));
    $files = new Filesystem;
    $files->ensureDirectoryExists($root . '/24/outline');
    $files->put($root . '/24/outline/check.svg', '<svg class="outline"></svg>');

    $provider = new HeroiconProvider($root, $files);

    expect($provider('check', 'unknown-variant'))->toContain('class="outline"');

    $files->deleteDirectory($root);
});

it('returns empty string when the SVG file does not exist', function () {
    $root = sys_get_temp_dir() . '/laradocs-heroicons-' . bin2hex(random_bytes(4));
    $files = new Filesystem;
    $files->ensureDirectoryExists($root . '/24/outline');

    $provider = new HeroiconProvider($root, $files);

    expect($provider('nonexistent-icon'))->toBe('');

    $files->deleteDirectory($root);
});

it('returns empty string for unsafe heroicon names', function () {
    $root = sys_get_temp_dir() . '/laradocs-heroicons-' . bin2hex(random_bytes(4));
    $files = new Filesystem;
    $files->ensureDirectoryExists($root . '/24');
    $files->put($root . '/24/secret.svg', '<svg></svg>');

    $provider = new HeroiconProvider($root, $files);

    expect($provider('../secret'))->toBe('')
        ->and($provider('solid/check'))->toBe('')
        ->and($provider('check.svg'))->toBe('');

    $files->deleteDirectory($root);
});

it('strips the XML declaration from returned SVG', function () {
    $root = sys_get_temp_dir() . '/laradocs-heroicons-' . bin2hex(random_bytes(4));
    $files = new Filesystem;
    $files->ensureDirectoryExists($root . '/24/outline');
    $files->put($root . '/24/outline/arrow-right.svg', "<?xml version=\"1.0\"?>\n<svg></svg>");

    $provider = new HeroiconProvider($root, $files);

    $svg = $provider('arrow-right');

    expect($svg)->not->toContain('<?xml')
        ->and($svg)->toContain('<svg>');

    $files->deleteDirectory($root);
});

it('detect() returns null when heroicons is not installed', function () {
    expect(HeroiconProvider::detect())->toBeNull();
});

it('detect() returns the path when heroicons is installed under node_modules', function () {
    $heroiconsPath = base_path('node_modules/heroicons');
    $files = new Filesystem;
    $files->ensureDirectoryExists($heroiconsPath);

    $result = HeroiconProvider::detect();

    $files->deleteDirectory(base_path('node_modules'));

    expect($result)->toBe($heroiconsPath);
});

<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Laradocs\Contracts\DocumentParser;
use Laradocs\Icons\Icon;
use Laradocs\Icons\IconRegistry;
use Laradocs\Laradocs;

function registerFakeIcons(): void
{
    app(IconRegistry::class)->register('heroicons', function (string $name, string $variant): string {
        return "<svg data-icon=\"{$name}\" data-variant=\"{$variant}\"></svg>";
    });
}

it('expands @icon() shorthand in markdown body', function () {
    registerFakeIcons();

    $html = app(DocumentParser::class)->parse("Look at this. @icon('arrow-long-left') - It's got an arrow.");

    expect($html)
        ->toContain('laradocs-icon')
        ->toContain('data-icon="arrow-long-left"');
});

it('passes a variant to @icon() when specified', function () {
    registerFakeIcons();

    $html = app(DocumentParser::class)->parse("@icon('check', variant: 'solid')");

    expect($html)->toContain('data-variant="solid"');
});

it('passes a set to @icon() when specified', function () {
    app(IconRegistry::class)->register('phosphor', function (string $name): string {
        return "<svg data-set=\"phosphor\" data-icon=\"{$name}\"></svg>";
    });

    $html = app(DocumentParser::class)->parse("@icon('arrow', set: 'phosphor')");

    expect($html)
        ->toContain('data-set="phosphor"')
        ->toContain('data-icon="arrow"');
});

it('renders nothing for an @icon() call with no matching set', function () {
    $html = app(DocumentParser::class)->parse("before @icon('missing-icon') after");

    expect($html)
        ->not->toContain('laradocs-icon')
        ->toContain('before')
        ->toContain('after');
});

it('leaves an @icon() call with an empty name verbatim', function () {
    registerFakeIcons();

    $html = app(DocumentParser::class)->parse('before @icon( ) after');

    expect($html)
        ->not->toContain('laradocs-icon')
        ->toContain('@icon( )');
});

it('leaves @icon() inside code blocks untouched', function () {
    registerFakeIcons();

    $html = app(DocumentParser::class)->parse("```\n@icon('arrow')\n```");

    expect($html)
        ->toContain("@icon('arrow')")
        ->not->toContain('laradocs-icon');
});

it('leaves @icon() inside inline code untouched', function () {
    registerFakeIcons();

    $html = app(DocumentParser::class)->parse("Use `@icon('arrow')` literally.");

    expect($html)
        ->toContain("@icon('arrow')")
        ->not->toContain('laradocs-icon');
});

it('renders an icon via the @docs(icon) macro', function () {
    registerFakeIcons();

    $html = app(DocumentParser::class)->parse("@docs('icon', 'arrow-long-right')");

    expect($html)
        ->toContain('laradocs-icon')
        ->toContain('data-icon="arrow-long-right"');
});

it('renders an icon via the @docs(icon:heroicons) macro', function () {
    registerFakeIcons();

    $html = app(DocumentParser::class)->parse("@docs('icon:heroicons', 'arrow-long-right')");

    expect($html)
        ->toContain('laradocs-icon')
        ->toContain('data-icon="arrow-long-right"');
});

it('uses the variant argument in the icon macro', function () {
    registerFakeIcons();

    $html = app(DocumentParser::class)->parse("@docs('icon', 'check', variant: 'solid')");

    expect($html)->toContain('data-variant="solid"');
});

it('can register a custom icon set via Laradocs::registerIconSet()', function () {
    app(Laradocs::class)->registerIconSet('custom', function (string $name): string {
        return "<svg data-custom=\"{$name}\"></svg>";
    });

    $html = app(DocumentParser::class)->parse("@icon('star', set: 'custom')");

    expect($html)->toContain('data-custom="star"');
});

it('renders an icon via the Icon view helper', function () {
    registerFakeIcons();

    $html = Icon::render('arrow-long-right');

    expect($html)
        ->toContain('laradocs-icon')
        ->toContain('data-icon="arrow-long-right"');
});

it('renders empty string from Icon helper for null name', function () {
    expect(Icon::render(null))->toBe('');
});

it('passes variant through the Icon view helper', function () {
    registerFakeIcons();

    $html = Icon::render('check', 'solid');

    expect($html)->toContain('data-variant="solid"');
});

it('registers the heroicons set from a configured path', function () {
    $root = sys_get_temp_dir() . '/laradocs-heroicons-' . bin2hex(random_bytes(4));
    $files = new Filesystem;
    $files->ensureDirectoryExists($root . '/24/outline');
    $files->put($root . '/24/outline/arrow-long-right.svg', '<svg data-real="1"></svg>');

    config()->set('laradocs.icons.heroicons.path', $root);
    app()->forgetInstance(IconRegistry::class);

    $registry = app(IconRegistry::class);

    expect($registry->has('heroicons'))->toBeTrue()
        ->and($registry->render('arrow-long-right'))->toContain('data-real="1"');

    $files->deleteDirectory($root);
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

it('scaffolds a starter document with docs:install', function () {
    $path = sys_get_temp_dir() . '/laradocs-install-' . bin2hex(random_bytes(4));
    config()->set('laradocs.docs.path', $path);
    $this->tempDocs[] = $path;

    $this->artisan('docs:install')->assertSuccessful();

    expect(File::exists($path . '/index.md'))->toBeTrue()
        ->and(File::get($path . '/index.md'))->toContain('title: Welcome');
});

it('does not clobber an existing index without --force', function () {
    $path = sys_get_temp_dir() . '/laradocs-install-' . bin2hex(random_bytes(4));
    config()->set('laradocs.docs.path', $path);
    $this->tempDocs[] = $path;
    File::ensureDirectoryExists($path);
    File::put($path . '/index.md', 'ORIGINAL');

    $this->artisan('docs:install')->assertSuccessful();

    expect(File::get($path . '/index.md'))->toBe('ORIGINAL');
});

it('creates a doc with make:doc including front-matter', function () {
    $path = sys_get_temp_dir() . '/laradocs-make-' . bin2hex(random_bytes(4));
    config()->set('laradocs.docs.path', $path);
    $this->tempDocs[] = $path;

    $this->artisan('make:doc', ['name' => 'guide/getting-started', '--group' => 'Basics', '--order' => 2])
        ->assertSuccessful();

    $file = $path . '/guide/getting-started.md';

    expect(File::exists($file))->toBeTrue()
        ->and(File::get($file))->toContain('title: Getting Started')
        ->and(File::get($file))->toContain('group: Basics')
        ->and(File::get($file))->toContain('order: 2');
});

it('refuses to overwrite with make:doc unless forced', function () {
    $path = sys_get_temp_dir() . '/laradocs-make-' . bin2hex(random_bytes(4));
    config()->set('laradocs.docs.path', $path);
    $this->tempDocs[] = $path;
    File::ensureDirectoryExists($path);
    File::put($path . '/x.md', 'KEEP');

    $this->artisan('make:doc', ['name' => 'x'])->assertFailed();

    expect(File::get($path . '/x.md'))->toBe('KEEP');
});

it('reports package details to the about command', function () {
    $this->artisan('about', ['--only' => 'laradocs'])
        ->assertSuccessful()
        ->expectsOutputToContain('Laradocs');
});

it('renders the make:doc stub through Blade with all options commented', function () {
    $path = sys_get_temp_dir() . '/laradocs-stub-' . bin2hex(random_bytes(4));
    config()->set('laradocs.docs.path', $path);
    $this->tempDocs[] = $path;

    $this->artisan('make:doc', ['name' => 'guide/intro'])->assertSuccessful();

    $contents = File::get($path . '/guide/intro.md');

    expect($contents)
        ->toContain('title: Intro')
        ->toContain('# group: Guides')
        ->toContain('# order: 1')
        ->toContain('# description: A short summary')
        ->toContain('# slug: custom-url')
        ->toContain('# hidden: true')
        ->toContain('# badge: New')
        ->toContain('# icon: book')
        ->toContain('# tags: [intro, basics]')
        ->toContain('# updated_at:')
        ->toContain('# author: Jane Doe')
        ->toContain('# layout: docs')
        ->toContain('# image:')
        ->toContain('# redirect:');
});

it('prefers a published stub over the package one', function () {
    $stubsDir = base_path('stubs/laradocs');
    File::ensureDirectoryExists($stubsDir);
    File::put($stubsDir . '/page.blade.php', "---\ntitle: {{ \$title }}\ncustom: yes\n---\n\n# Override");
    $this->tempDocs[] = $stubsDir;

    $path = sys_get_temp_dir() . '/laradocs-override-' . bin2hex(random_bytes(4));
    config()->set('laradocs.docs.path', $path);
    $this->tempDocs[] = $path;

    $this->artisan('make:doc', ['name' => 'x'])->assertSuccessful();

    expect(File::get($path . '/x.md'))
        ->toContain('custom: yes')
        ->toContain('# Override')
        ->not->toContain('# description:');
});

it('exposes a publish tag for the stubs', function () {
    expect(ServiceProvider::pathsToPublish(null, 'laradocs-stubs'))
        ->not->toBeEmpty();
});

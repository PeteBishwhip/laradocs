<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

it('scaffolds a starter document with laradocs:install', function () {
    $path = sys_get_temp_dir() . '/laradocs-install-' . bin2hex(random_bytes(4));
    config()->set('laradocs.docs.path', $path);
    $this->tempDocs[] = $path;

    $this->artisan('laradocs:install')->assertSuccessful();

    expect(File::exists($path . '/index.md'))->toBeTrue()
        ->and(File::get($path . '/index.md'))->toContain('title: Welcome');
});

it('does not clobber an existing index without --force', function () {
    $path = sys_get_temp_dir() . '/laradocs-install-' . bin2hex(random_bytes(4));
    config()->set('laradocs.docs.path', $path);
    $this->tempDocs[] = $path;
    File::ensureDirectoryExists($path);
    File::put($path . '/index.md', 'ORIGINAL');

    $this->artisan('laradocs:install')->assertSuccessful();

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

// docs:check — link checker, orphan finder, redirect cycle detection

it('docs:check exits 0 when docs are clean', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n\n# Home\n",
        'guide/intro.md' => "---\ntitle: Intro\n---\n\nSee the [home page](/docs).\n",
    ]);

    $this->artisan('docs:check')->assertSuccessful();
});

it('docs:check exits non-zero on broken internal links', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n\n[broken](/docs/nowhere)\n",
    ]);

    $this->artisan('docs:check')->assertFailed();
});

it('docs:check reports broken links via --json', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n\n[broken](/docs/nowhere)\n",
    ]);

    $exit = \Artisan::call('docs:check', ['--json' => true]);
    $data = json_decode(\Artisan::output(), true);

    expect($exit)->not->toBe(0)
        ->and($data['broken_links'])->toHaveCount(1)
        ->and($data['broken_links'][0]['slug'])->toBe('nowhere')
        ->and($data['summary']['total'])->toBe(1);
});

it('docs:check ignores external and anchor-only links', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n\n[ext](https://example.com) [anchor](#section)\n",
    ]);

    $this->artisan('docs:check')->assertSuccessful();
});

it('docs:check strips anchors from internal links before resolving', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n\n[link](/docs/guide/intro#section)\n",
        'guide/intro.md' => "---\ntitle: Intro\n---\n\n# Section\n",
    ]);

    $this->artisan('docs:check')->assertSuccessful();
});

it('docs:check detects redirect cycles', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n\n# Home\n",
        'a.md' => "---\ntitle: A\nredirect: b\n---\n",
        'b.md' => "---\ntitle: B\nredirect: a\n---\n",
    ]);

    $this->artisan('docs:check')->assertFailed();
});

it('docs:check reports redirect cycles via --json', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n\n# Home\n",
        'a.md' => "---\ntitle: A\nredirect: b\n---\n",
        'b.md' => "---\ntitle: B\nredirect: a\n---\n",
    ]);

    $exit = \Artisan::call('docs:check', ['--json' => true]);
    $data = json_decode(\Artisan::output(), true);

    expect($exit)->not->toBe(0)
        ->and($data['redirect_cycles'])->toHaveCount(1)
        ->and($data['redirect_cycles'][0]['cycle'])->toContain('a')
        ->and($data['redirect_cycles'][0]['cycle'])->toContain('b');
});

it('docs:check ignores redirects to non-existent slugs for cycle detection', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n\n# Home\n",
        'a.md' => "---\ntitle: A\nredirect: missing\n---\n",
    ]);

    $exit = \Artisan::call('docs:check', ['--json' => true]);
    $data = json_decode(\Artisan::output(), true);

    expect($exit)->toBe(0)
        ->and($data['redirect_cycles'])->toBeEmpty();
});

it('docs:check does not flag hidden docs as orphans', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n\n# Home\n",
        'secret.md' => "---\ntitle: Secret\nhidden: true\n---\n",
    ]);

    $exit = \Artisan::call('docs:check', ['--json' => true]);
    $data = json_decode(\Artisan::output(), true);

    expect($exit)->toBe(0)
        ->and($data['orphans'])->toBeEmpty();
});

it('docs:check --json summary counts total findings', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n\n[broken](/docs/gone)\n",
        'a.md' => "---\ntitle: A\nredirect: b\n---\n",
        'b.md' => "---\ntitle: B\nredirect: a\n---\n",
    ]);

    $exit = \Artisan::call('docs:check', ['--json' => true]);
    $data = json_decode(\Artisan::output(), true);

    expect($exit)->not->toBe(0)
        ->and($data['summary']['broken_links'])->toBe(1)
        ->and($data['summary']['redirect_cycles'])->toBe(1)
        ->and($data['summary']['total'])->toBeGreaterThanOrEqual(2);
});

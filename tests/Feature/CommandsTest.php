<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Laradocs\Icons\IconRegistry;

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

    $exit = Artisan::call('docs:check', ['--json' => true]);
    $data = json_decode(Artisan::output(), true);

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

    $exit = Artisan::call('docs:check', ['--json' => true]);
    $data = json_decode(Artisan::output(), true);

    expect($exit)->not->toBe(0)
        ->and($data['redirect_cycles'])->toHaveCount(1)
        ->and($data['redirect_cycles'][0]['cycle'])->toContain('a')
        ->and($data['redirect_cycles'][0]['cycle'])->toContain('b');
});

it('docs:check resolves prefixed redirect targets when detecting cycles', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n\n# Home\n",
        'a.md' => "---\ntitle: A\nredirect: /docs/b\n---\n",
        'b.md' => "---\ntitle: B\nredirect: /docs/a\n---\n",
    ]);

    $exit = Artisan::call('docs:check', ['--json' => true]);
    $data = json_decode(Artisan::output(), true);

    expect($exit)->not->toBe(0)
        ->and($data['redirect_cycles'])->toHaveCount(1);
});

it('docs:check ignores redirects to non-existent slugs for cycle detection', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n\n# Home\n",
        'a.md' => "---\ntitle: A\nredirect: missing\n---\n",
    ]);

    $exit = Artisan::call('docs:check', ['--json' => true]);
    $data = json_decode(Artisan::output(), true);

    expect($exit)->toBe(0)
        ->and($data['redirect_cycles'])->toBeEmpty();
});

it('docs:check flags an unreachable hidden page as an orphan', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n\n# Home\n",
        'secret.md' => "---\ntitle: Secret\nhidden: true\n---\n",
    ]);

    $exit = Artisan::call('docs:check', ['--json' => true]);
    $data = json_decode(Artisan::output(), true);

    expect($exit)->not->toBe(0)
        ->and($data['orphans'])->toHaveCount(1)
        ->and($data['orphans'][0]['slug'])->toBe('secret');
});

it('docs:check does not flag a hidden page that another page links to', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n\nSee the [secret](/docs/secret).\n",
        'secret.md' => "---\ntitle: Secret\nhidden: true\n---\n",
    ]);

    $exit = Artisan::call('docs:check', ['--json' => true]);
    $data = json_decode(Artisan::output(), true);

    expect($exit)->toBe(0)
        ->and($data['orphans'])->toBeEmpty();
});

it('docs:check renders orphan findings in the default output', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n\n# Home\n",
        'secret.md' => "---\ntitle: Secret\nhidden: true\n---\n",
    ]);

    $this->artisan('docs:check')
        ->assertFailed()
        ->expectsOutputToContain('ORPHAN');
});

it('docs:check --json summary counts total findings', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n\n[broken](/docs/gone)\n",
        'a.md' => "---\ntitle: A\nredirect: b\n---\n",
        'b.md' => "---\ntitle: B\nredirect: a\n---\n",
    ]);

    $exit = Artisan::call('docs:check', ['--json' => true]);
    $data = json_decode(Artisan::output(), true);

    expect($exit)->not->toBe(0)
        ->and($data['summary']['broken_links'])->toBe(1)
        ->and($data['summary']['redirect_cycles'])->toBe(1)
        ->and($data['summary']['total'])->toBeGreaterThanOrEqual(2);
});

// docs:lint — front-matter validator

it('docs:lint exits 0 when all docs pass', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n\n# Home\n",
        'guide/intro.md' => "---\ntitle: Intro\nupdated_at: 2026-01-15\n---\n",
    ]);

    $this->artisan('docs:lint')->assertSuccessful();
});

it('docs:lint fails when a required field is missing', function () {
    $this->makeDocs([
        '_index.md' => "---\n---\n\n# Home\n",
    ]);

    $this->artisan('docs:lint')->assertFailed();
});

it('docs:lint reports missing fields via --json', function () {
    $this->makeDocs([
        '_index.md' => "---\n---\n\n# Home\n",
    ]);

    $exit = Artisan::call('docs:lint', ['--json' => true]);
    $data = json_decode(Artisan::output(), true);

    expect($exit)->not->toBe(0)
        ->and($data['missing_fields'])->toHaveCount(1)
        ->and($data['missing_fields'][0]['field'])->toBe('title')
        ->and($data['summary']['missing_fields'])->toBe(1)
        ->and($data['summary']['total'])->toBe(1);
});

it('docs:lint required fields are config-driven', function () {
    config()->set('laradocs.lint.required', ['title', 'description']);

    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n",
    ]);

    $exit = Artisan::call('docs:lint', ['--json' => true]);
    $data = json_decode(Artisan::output(), true);

    expect($exit)->not->toBe(0)
        ->and($data['missing_fields'])->toHaveCount(1)
        ->and($data['missing_fields'][0]['field'])->toBe('description');
});

it('docs:lint passes when required list is empty', function () {
    config()->set('laradocs.lint.required', []);

    $this->makeDocs([
        '_index.md' => "---\n---\n\n# No title\n",
    ]);

    $this->artisan('docs:lint')->assertSuccessful();
});

it('docs:lint detects slug collisions', function () {
    $this->makeDocs([
        'intro.md' => "---\ntitle: Intro A\n---\n",
        'sub/intro.md' => "---\ntitle: Intro B\nslug: intro\n---\n",
    ]);

    $exit = Artisan::call('docs:lint', ['--json' => true]);
    $data = json_decode(Artisan::output(), true);

    expect($exit)->not->toBe(0)
        ->and($data['slug_collisions'])->toHaveCount(1)
        ->and($data['slug_collisions'][0]['slug'])->toBe('intro')
        ->and($data['slug_collisions'][0]['paths'])->toHaveCount(2);
});

it('docs:lint detects unknown layout names when layouts allowlist is set', function () {
    config()->set('laradocs.lint.layouts', ['docs', 'landing']);

    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\nlayout: ghost\n---\n",
    ]);

    $exit = Artisan::call('docs:lint', ['--json' => true]);
    $data = json_decode(Artisan::output(), true);

    expect($exit)->not->toBe(0)
        ->and($data['unknown_layouts'])->toHaveCount(1)
        ->and($data['unknown_layouts'][0]['layout'])->toBe('ghost');
});

it('docs:lint skips layout check when layouts allowlist is empty', function () {
    config()->set('laradocs.lint.layouts', []);

    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\nlayout: anything\n---\n",
    ]);

    $this->artisan('docs:lint')->assertSuccessful();
});

it('docs:lint accepts a known layout name', function () {
    config()->set('laradocs.lint.layouts', ['docs', 'landing']);

    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\nlayout: docs\n---\n",
    ]);

    $this->artisan('docs:lint')->assertSuccessful();
});

it('docs:lint accepts a valid updated_at date', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\nupdated_at: 2026-03-15\n---\n",
    ]);

    $this->artisan('docs:lint')->assertSuccessful();
});

it('docs:lint accepts a valid updated_at datetime', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\nupdated_at: '2026-03-15 10:30:00'\n---\n",
    ]);

    $this->artisan('docs:lint')->assertSuccessful();
});

it('docs:lint flags an invalid updated_at format', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\nupdated_at: 15th March 2026\n---\n",
    ]);

    $exit = Artisan::call('docs:lint', ['--json' => true]);
    $data = json_decode(Artisan::output(), true);

    expect($exit)->not->toBe(0)
        ->and($data['invalid_dates'])->toHaveCount(1)
        ->and($data['invalid_dates'][0]['value'])->toBe('15th March 2026');
});

it('docs:lint does not flag a missing updated_at', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n",
    ]);

    $this->artisan('docs:lint')->assertSuccessful();
});

it('docs:lint flags a front-matter icon when the set is unavailable', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\nicon: arrow-long-right\n---\n",
    ]);

    $exit = Artisan::call('docs:lint', ['--json' => true]);
    $data = json_decode(Artisan::output(), true);

    expect($exit)->not->toBe(0)
        ->and($data['unresolved_icons'])->toHaveCount(1)
        ->and($data['unresolved_icons'][0]['icon'])->toBe('arrow-long-right')
        ->and($data['unresolved_icons'][0]['set'])->toBe('heroicons')
        ->and($data['unresolved_icons'][0]['reason'])->toBe('set_unavailable')
        ->and($data['summary']['unresolved_icons'])->toBe(1);
});

it('docs:lint flags an inline @icon() call when the set is unavailable', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n\nClick @icon('plus') to add.\n",
    ]);

    $exit = Artisan::call('docs:lint', ['--json' => true]);
    $data = json_decode(Artisan::output(), true);

    expect($exit)->not->toBe(0)
        ->and($data['unresolved_icons'])->toHaveCount(1)
        ->and($data['unresolved_icons'][0]['icon'])->toBe('plus');
});

it('docs:lint ignores @icon() inside code blocks', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n\n```\n@icon('plus')\n```\n",
    ]);

    $this->artisan('docs:lint')->assertSuccessful();
});

it('docs:lint flags an unknown icon name when the set is available', function () {
    app(IconRegistry::class)->register('heroicons', function (string $name): string {
        return $name === 'real-icon' ? '<svg></svg>' : '';
    });

    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\nicon: made-up-icon\n---\n",
    ]);

    $exit = Artisan::call('docs:lint', ['--json' => true]);
    $data = json_decode(Artisan::output(), true);

    expect($exit)->not->toBe(0)
        ->and($data['unresolved_icons'])->toHaveCount(1)
        ->and($data['unresolved_icons'][0]['reason'])->toBe('unknown_icon');
});

it('docs:lint passes when a referenced icon resolves', function () {
    app(IconRegistry::class)->register('heroicons', function (): string {
        return '<svg></svg>';
    });

    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\nicon: arrow-long-right\n---\n\nUse @icon('check').\n",
    ]);

    $this->artisan('docs:lint')->assertSuccessful();
});

it('docs:lint skips the icon check when laradocs.lint.icons is false', function () {
    config()->set('laradocs.lint.icons', false);

    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\nicon: arrow-long-right\n---\n",
    ]);

    $this->artisan('docs:lint')->assertSuccessful();
});

it('docs:lint --json summary counts all findings', function () {
    config()->set('laradocs.lint.layouts', ['docs']);

    $this->makeDocs([
        '_index.md' => "---\n---\n",
        'guide.md' => "---\ntitle: Guide\nlayout: ghost\nupdated_at: not-a-date\n---\n",
    ]);

    $exit = Artisan::call('docs:lint', ['--json' => true]);
    $data = json_decode(Artisan::output(), true);

    expect($exit)->not->toBe(0)
        ->and($data['summary']['missing_fields'])->toBeGreaterThanOrEqual(1)
        ->and($data['summary']['unknown_layouts'])->toBe(1)
        ->and($data['summary']['invalid_dates'])->toBe(1)
        ->and($data['summary']['total'])->toBeGreaterThanOrEqual(3);
});

// laradocs:versions — tabular version listing

it('laradocs:versions lists detected versions with metadata columns', function () {
    config()->set('laradocs.versions.enabled', true);
    config()->set('laradocs.versions.strategy', 'config');
    config()->set('laradocs.versions.available', [
        'v2.0' => ['label' => 'Version 2', 'stable' => true],
        'v1.0' => ['label' => 'Version 1', 'deprecated' => true],
    ]);

    $exit = Artisan::call('laradocs:versions');
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('key')
        ->and($output)->toContain('label')
        ->and($output)->toContain('semver')
        ->and($output)->toContain('stable')
        ->and($output)->toContain('deprecated')
        ->and($output)->toContain('hidden')
        ->and($output)->toContain('latest')
        ->and($output)->toContain('Version 2')
        ->and($output)->toContain('Version 1');
});

it('laradocs:versions renders boolean columns as yes/no and flags the latest', function () {
    config()->set('laradocs.versions.enabled', true);
    config()->set('laradocs.versions.strategy', 'config');
    config()->set('laradocs.versions.available', [
        'v2.0' => ['label' => 'Version 2'],
        'v1.0' => ['label' => 'Version 1', 'deprecated' => true],
    ]);

    $exit = Artisan::call('laradocs:versions');
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('yes')
        ->and($output)->toContain('no');
});

it('laradocs:versions prints a friendly message when no versions are detected', function () {
    config()->set('laradocs.versions.enabled', false);
    config()->set('laradocs.versions.strategy', 'config');
    config()->set('laradocs.versions.available', []);

    $this->artisan('laradocs:versions')
        ->assertSuccessful()
        ->expectsOutputToContain('No documentation versions detected');
});

it('laradocs:versions is registered and appears in the command list', function () {
    expect(Artisan::all())->toHaveKey('laradocs:versions');
});

it('docs:lint renders human-readable output for findings', function () {
    $this->makeDocs([
        '_index.md' => "---\n---\n\n# Home\n",
    ]);

    $this->artisan('docs:lint')
        ->assertFailed()
        ->expectsOutputToContain('MISSING FIELD');
});

it('docs:lint renders every finding type in human-readable output', function () {
    config()->set('laradocs.lint.layouts', ['docs']);

    $this->makeDocs([
        'intro.md' => "---\ntitle: Intro A\n---\n",
        'sub/intro.md' => "---\ntitle: Intro B\nslug: intro\n---\n",
        'guide.md' => "---\ntitle: Guide\nlayout: ghost\nupdated_at: not-a-date\nicon: arrow-long-right\n---\n",
    ]);

    $this->artisan('docs:lint')
        ->assertFailed()
        ->expectsOutputToContain('SLUG COLLISION')
        ->expectsOutputToContain('UNKNOWN LAYOUT')
        ->expectsOutputToContain('INVALID DATE')
        ->expectsOutputToContain('UNRESOLVED ICON');
});

it('docs:lint hints at the npm install for a missing heroicons set', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\nicon: arrow-long-right\n---\n",
    ]);

    $this->artisan('docs:lint')
        ->assertFailed()
        ->expectsOutputToContain('npm install heroicons');
});

it('docs:lint reports a missing custom set without the npm hint', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n\n@icon('star', set: 'phosphor')\n",
    ]);

    $this->artisan('docs:lint')
        ->assertFailed()
        ->expectsOutputToContain('icon set "phosphor" is not available')
        ->doesntExpectOutputToContain('npm install');
});

it('docs:lint names the missing icon when the set is available', function () {
    app(IconRegistry::class)->register('heroicons', function (): string {
        return '';
    });

    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\nicon: made-up\n---\n",
    ]);

    $this->artisan('docs:lint')
        ->assertFailed()
        ->expectsOutputToContain('not found in icon set');
});

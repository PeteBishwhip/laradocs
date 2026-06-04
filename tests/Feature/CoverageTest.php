<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Laradocs\Contracts\DocumentParser;
use Laradocs\Laradocs;

it('shows the empty state when there are no documents', function () {
    config()->set('laradocs.docs.path', '/definitely/not/here');

    $this->get('/docs')->assertOk()->assertSee('Your documentation, ready when you are.');
});

it('falls back to the first visible doc when there is no root index', function () {
    $this->makeDocs([
        'first.md' => "---\ntitle: First\norder: 1\n---\n# First page\n",
        'second.md' => "---\ntitle: Second\norder: 2\n---\n# Second page\n",
    ]);

    $this->get('/docs')->assertOk()->assertSee('First page');
});

it('404s for an unknown asset file', function () {
    $this->get('/docs/_laradocs/asset/evil.php')->assertNotFound();
});

it('serves the bundled javascript', function () {
    $this->get('/docs/_laradocs/asset/laradocs.js')->assertOk();
});

it('renders every callout type and a vimeo embed', function () {
    $parser = app(DocumentParser::class);

    foreach (['tip', 'important', 'warning', 'danger', 'caution'] as $type) {
        expect($parser->parse('> [!' . strtoupper($type) . "]\n> Body"))
            ->toContain('laradocs-callout-' . $type);
    }

    expect($parser->parse('[v](https://vimeo.com/12345)'))->toContain('player.vimeo.com/video/12345');
});

it('leaves a non-video link untouched', function () {
    expect(app(DocumentParser::class)->parse('[home](https://example.com)'))
        ->toContain('href="https://example.com"')
        ->and(app(DocumentParser::class)->parse('[home](https://example.com)'))
        ->not->toContain('laradocs-embed');
});

it('renders the home document through the index route with caching', function () {
    $this->makeDocs(['_index.md' => "---\ntitle: Home\n---\n# Homepage\n"]);

    $laradocs = app(Laradocs::class);

    expect($laradocs->home()?->html)->toContain('Homepage');
    $this->get('/docs')->assertOk()->assertSee('Homepage');
});

it('overwrites with make:doc when forced', function () {
    $path = sys_get_temp_dir() . '/laradocs-force-' . bin2hex(random_bytes(4));
    config()->set('laradocs.docs.path', $path);
    $this->tempDocs[] = $path;
    File::ensureDirectoryExists($path);
    File::put($path . '/x.md', 'OLD');

    $this->artisan('make:doc', ['name' => 'x', '--force' => true, '--title' => 'Fresh'])->assertSuccessful();

    expect(File::get($path . '/x.md'))->toContain('title: Fresh');
});

it('reinstalls over an existing file with --force', function () {
    $path = sys_get_temp_dir() . '/laradocs-reinstall-' . bin2hex(random_bytes(4));
    config()->set('laradocs.docs.path', $path);
    $this->tempDocs[] = $path;
    File::ensureDirectoryExists($path);
    File::put($path . '/index.md', 'OLD');

    $this->artisan('laradocs:install', ['--force' => true])->assertSuccessful();

    expect(File::get($path . '/index.md'))->toContain('Welcome');
});

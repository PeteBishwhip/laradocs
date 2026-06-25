<?php

declare(strict_types=1);

use Illuminate\Contracts\Routing\Registrar;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Laradocs\Cache\DocumentCache;
use Laradocs\Contracts\DocumentParser;
use Laradocs\Documents\TreeNode;
use Laradocs\Extensions\CodeBlockExtension;
use Laradocs\Extensions\HeadingAnchorExtension;
use Laradocs\Laradocs;
use Laradocs\Macros\MacroRegistry;
use Laradocs\Routing\DocumentRouter;
use Laradocs\Support\CodeAwareReplacer;
use Laradocs\Variables\VariableRegistry;

it('exposes the variables(), macro(), variableRegistry() and cache() helpers on Laradocs', function () {
    $laradocs = app(Laradocs::class);

    $laradocs->variables(['from_array' => 'A']);
    $laradocs->variables(fn (): array => ['from_closure' => 'B']);
    $laradocs->macro('coverage_macro', fn (): string => '<em>x</em>');

    expect($laradocs->variableRegistry())->toBeInstanceOf(VariableRegistry::class)
        ->and($laradocs->macroRegistry())->toBeInstanceOf(MacroRegistry::class)
        ->and($laradocs->cache())->toBeInstanceOf(DocumentCache::class)
        ->and($laradocs->variableValues())->toMatchArray([
            'from_array' => 'A',
            'from_closure' => 'B',
        ])
        ->and($laradocs->macroRegistry()->has('coverage_macro'))->toBeTrue();
});

it('emits unbalanced @docs( verbatim and stops scanning', function () {
    $parser = app(DocumentParser::class);

    expect($parser->parse('@docs(no-closing-paren left here'))
        ->toContain('@docs(no-closing-paren left here');
});

it('parses macro calls with nested parentheses', function () {
    app(Laradocs::class)->macro('coverage_nested', fn (array $arguments): string => 'OK');

    expect(app(DocumentParser::class)->parse("@docs('coverage_nested', value: (1+2))"))
        ->toContain('OK');
});

it('drops an empty @docs() call', function () {
    expect(trim(app(DocumentParser::class)->parse('@docs()')))->toBe('');
});

it('passes positional and boolean arguments to macros', function () {
    app(Laradocs::class)->macro('coverage_args', function (array $arguments): string {
        return '[' . implode('|', array_map(
            fn (mixed $value): string => match (true) {
                $value === true => 'TRUE',
                $value === false => 'FALSE',
                default => (string) $value,
            },
            $arguments
        )) . ']';
    });

    expect(app(DocumentParser::class)->parse("@docs('coverage_args', 'first', enabled: true, hidden: false)"))
        ->toContain('[first|TRUE|FALSE]');
});

it('accepts unquoted macro names and bare positional args', function () {
    app(Laradocs::class)->macro('barename', fn (array $arguments): string => '[' . ($arguments[0] ?? '') . ']');

    expect(app(DocumentParser::class)->parse('@docs(barename, bareval)'))
        ->toContain('[bareval]');
});

it('passes a single-character macro name through unquote unchanged', function () {
    // The unquote helper bails out when the value is shorter than the two
    // characters needed for a matching quote pair — exercises that early-out.
    app(Laradocs::class)->macro('a', fn (): string => '<b>letter</b>');

    expect(app(DocumentParser::class)->parse('@docs(a)'))
        ->toContain('letter');
});

it('falls back to section when a heading slug would be empty', function () {
    $html = (new HeadingAnchorExtension)->processHtml('<h2></h2>');

    expect($html)->toContain('id="section"');
});

it('disambiguates repeated heading slugs with numeric suffixes', function () {
    $html = (new HeadingAnchorExtension)->processHtml('<h2>Foo</h2><h2>Foo</h2><h2>Foo</h2>');

    expect($html)->toContain('id="foo"')
        ->and($html)->toContain('id="foo-1"')
        ->and($html)->toContain('id="foo-2"');
});

it('leaves pre blocks without a code child unlabelled', function () {
    $html = (new CodeBlockExtension)->processHtml('<pre>just text</pre>');

    expect($html)->toContain('laradocs-code')
        ->and($html)->toContain('just text')
        ->and($html)->not->toContain('data-language');
});

it('blanks variables that resolve to a non-scalar value', function () {
    app(Laradocs::class)->share('nested', ['cant' => 'serialize']);

    expect(app(DocumentParser::class)->parse('Value: {{ nested }}!'))
        ->toContain('Value: !');
});

it('embeds youtube /embed/ urls and youtube-nocookie paths', function () {
    $parser = app(DocumentParser::class);

    expect($parser->parse('[w](https://www.youtube.com/embed/AbCdEfG)'))
        ->toContain('youtube-nocookie.com/embed/AbCdEfG')
        ->and($parser->parse('[w](https://youtube-nocookie.com/embed/HiJkLmN)'))
        ->toContain('youtube-nocookie.com/embed/HiJkLmN');
});

it('treats an unbalanced inline backtick run as text and keeps scanning', function () {
    $output = CodeAwareReplacer::apply(
        'a `unbalanced',
        fn (string $text): string => '<<' . $text . '>>'
    );

    expect($output)->toBe('<<a `unbalanced>>');
});

it('applies the configured route domain', function () {
    Route::setRoutes(new RouteCollection);

    (new DocumentRouter)->register(app(Registrar::class), [
        'prefix' => 'docs',
        'name' => 'laradocs-coverage.',
        'middleware' => ['web'],
        'domain' => 'docs.example.test',
    ]);

    $route = collect(Route::getRoutes()->getRoutes())
        ->first(fn ($r) => $r->getName() === 'laradocs-coverage.index');

    expect($route)->not->toBeNull()
        ->and($route->getDomain())->toBe('docs.example.test');
});

it('redirects absolute URLs in the redirect front-matter through without rewriting', function () {
    $this->makeDocs([
        'jump.md' => "---\nredirect: https://example.com/elsewhere\n---\nignored body\n",
    ]);

    $this->get('/docs/jump')->assertRedirect('https://example.com/elsewhere');
});

it('returns 404 for a known asset whose file is missing on disk', function () {
    $original = dirname(__DIR__, 2) . '/resources/dist/laradocs.js';
    $backup = $original . '.coverage-backup';

    expect(file_exists($original))->toBeTrue();
    rename($original, $backup);

    try {
        $this->get('/docs/_laradocs/asset/laradocs.js')->assertNotFound();
    } finally {
        rename($backup, $original);
    }
});

it('reports a TreeNode as a section when it has any children', function () {
    $leaf = new TreeNode('Leaf', 'leaf');
    $section = new TreeNode('Section', 'section');
    $section->addChild($leaf);

    expect($section->isSection())->toBeTrue()
        ->and($leaf->isSection())->toBeFalse();
});

it('renders updated_at as a formatted date, not a raw number', function () {
    // Quoted dates come through as strings; verify the human-readable format.
    $this->makeDocs([
        'page.md' => "---\ntitle: My Page\nupdated_at: \"2026-06-21\"\n---\nBody\n",
    ]);

    $this->get('/docs/page')
        ->assertOk()
        ->assertSee('Last updated')
        ->assertDontSee('1782000000');
});

it('renders unquoted updated_at (YAML integer timestamp) as a formatted date', function () {
    // Symfony YAML converts a bare `2026-06-21` to a Unix timestamp integer.
    // Simulate what that looks like after nullableString() casts it to string.
    $timestamp = mktime(0, 0, 0, 6, 21, 2026);
    $this->makeDocs([
        'page.md' => "---\ntitle: My Page\nupdated_at: {$timestamp}\n---\nBody\n",
    ]);

    $this->get('/docs/page')
        ->assertOk()
        ->assertSee('Last updated')
        ->assertDontSee((string) $timestamp);
});

it('respects the laradocs.locale.date_format config when rendering updated_at', function () {
    $this->makeDocs([
        'page.md' => "---\ntitle: My Page\nupdated_at: \"2026-06-21\"\n---\nBody\n",
    ]);

    config()->set('laradocs.locale.date_format', 'Y-m-d');

    $this->get('/docs/page')
        ->assertOk()
        ->assertSee('2026-06-21');
});

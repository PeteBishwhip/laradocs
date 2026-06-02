<?php

declare(strict_types=1);

use Laradocs\Contracts\DocumentParser;
use Laradocs\Laradocs;
use Laradocs\Routing\SlugResolver;
use Laradocs\Support\CodeAwareReplacer;
use Laradocs\Support\Url;

it('escapes interpolated variable values to prevent stored XSS', function () {
    app(Laradocs::class)->share('bio', '<img src=x onerror=alert(1)>');

    $html = app(DocumentParser::class)->parse('Bio: {{ bio }}');

    expect($html)->not->toContain('<img src=x')
        ->and($html)->toContain('&lt;img');
});

it('blocks javascript: and data: URIs in the button macro', function () {
    $parser = app(DocumentParser::class);

    expect($parser->parse("@docs('button', text: 'x', href: 'javascript:alert(1)')"))
        ->toContain('href="#"')
        ->and($parser->parse("@docs('button', text: 'x', href: 'https://laravel.com')"))
        ->toContain('href="https://laravel.com"');
});

it('allows safe and relative URLs through the Url guard', function () {
    expect(Url::safe('https://example.com'))->toBe('https://example.com')
        ->and(Url::safe('/docs/intro'))->toBe('/docs/intro')
        ->and(Url::safe('mailto:a@b.com'))->toBe('mailto:a@b.com')
        ->and(Url::safe('#anchor'))->toBe('#anchor')
        ->and(Url::safe('javascript:alert(1)'))->toBe('#')
        ->and(Url::safe('data:text/html,<script>'))->toBe('#')
        ->and(Url::safe('   '))->toBe('#');
});

it('neutralises path traversal in metadata slugs', function () {
    $resolver = new SlugResolver('metadata');

    expect($resolver->resolve('guide/intro.md', '../../secret'))->toBe('secret')
        ->and($resolver->resolve('guide/intro.md', '/foo//bar/'))->toBe('foo/bar');
});

it('is backslash-safe when resolving slugs directly', function () {
    expect((new SlugResolver('filename'))->resolve('guide\\intro.md'))->toBe('guide/intro');
});

it('treats quoted "false" as not hidden', function () {
    $this->makeDocs([
        'a.md' => "---\ntitle: A\nhidden: \"false\"\n---\nbody",
        'b.md' => "---\ntitle: B\nhidden: \"true\"\n---\nbody",
    ]);

    $docs = app(Laradocs::class)->all();

    expect($docs->findBySlug('a')?->isHidden())->toBeFalse()
        ->and($docs->findBySlug('b')?->isHidden())->toBeTrue();
});

it('protects interpolation inside double-backtick code spans and tilde fences', function () {
    $upper = fn (string $t): string => strtoupper($t);

    expect(CodeAwareReplacer::apply('a `` x ` {{v}} `` b', $upper))
        ->toBe('A `` x ` {{v}} `` B');

    // A line of 6 tildes inside a 3-tilde fence must not close it early.
    $input = "x\n~~~\n~~~~~~ {{v}}\n~~~\n{{v}}";
    expect(CodeAwareReplacer::apply($input, $upper))
        ->toBe("X\n~~~\n~~~~~~ {{v}}\n~~~\n{{V}}");
});

it('only embeds genuine youtube/vimeo hosts', function () {
    $parser = app(DocumentParser::class);

    expect($parser->parse('[a](https://evil.com/?x=youtube.com/watch?v=PAYLOAD)'))
        ->not->toContain('youtube-nocookie.com');

    expect($parser->parse('[a](https://www.youtube.com/watch?v=abc123)'))
        ->toContain('youtube-nocookie.com/embed/abc123');
});

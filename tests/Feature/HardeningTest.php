<?php

declare(strict_types=1);

use Laradocs\Contracts\DocumentParser;
use Laradocs\Extensions\VersionBlockExtension;
use Laradocs\Laradocs;
use Laradocs\Routing\SlugResolver;
use Laradocs\Support\CodeAwareReplacer;
use Laradocs\Support\Html;
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

it('strips the control characters browsers ignore before checking the scheme', function () {
    // Browsers drop tab/LF/CR anywhere in a URL and leading C0 controls, so a
    // scheme check that only looks at the raw string can be walked past.
    expect(Url::safe("jav\tascript:alert(1)"))->toBe('#')
        ->and(Url::safe("jav\nascript:alert(1)"))->toBe('#')
        ->and(Url::safe("jav\rascript:alert(1)"))->toBe('#')
        ->and(Url::safe("java\0script:alert(1)"))->toBe('#')
        ->and(Url::safe("\x01javascript:alert(1)"))->toBe('#')
        ->and(Url::safe("\x02\x03javascript:alert(1)"))->toBe('#')
        ->and(Url::safe("\x0Bjavascript:alert(1)"))->toBe('#');
});

it('leaves legitimate URLs untouched while normalising', function () {
    expect(Url::safe('https://example.com/a?b=c&d=e'))->toBe('https://example.com/a?b=c&d=e')
        ->and(Url::safe('tel:+3112345678'))->toBe('tel:+3112345678')
        ->and(Url::safe('../relative/page'))->toBe('../relative/page');
});

it('escapes the version-block spec so it cannot break out of its attribute', function () {
    $extension = new VersionBlockExtension;

    // PATTERN captures the spec as [^\]]+, which admits a double quote.
    $out = $extension->processMarkdown(
        ":::version-since[1.0\" onmouseover=\"alert(1)]\nBody.\n:::"
    );

    expect($out)->not->toContain('onmouseover="alert(1)"')
        ->and($out)->toContain('data-version-since="1.0&quot; onmouseover=&quot;alert(1)"');

    // The word still appears, inert, inside the attribute's value — what
    // matters is that the browser does not see it as an attribute of its own
    // once the parser (html_input => allow) has passed the block through.
    $div = Html::load(app(DocumentParser::class)->parse($out))
        ->getElementsByTagName('div')->item(0);

    expect($div?->hasAttribute('onmouseover'))->toBeFalse()
        ->and($div?->getAttribute('data-version-since'))->toBe('1.0" onmouseover="alert(1)');
});

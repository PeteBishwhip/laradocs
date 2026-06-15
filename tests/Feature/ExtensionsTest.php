<?php

declare(strict_types=1);

use Laradocs\Contracts\DocumentParser;
use Laradocs\Extensions\KatexExtension;
use Laradocs\Laradocs;

function render(string $markdown): string
{
    return app(DocumentParser::class)->parse($markdown);
}

/**
 * Write an executable PHP script that stands in for the `node` binary the
 * KaTeX extension shells out to. It receives the temp script path as $argv[1]
 * and the JSON expression batch as $argv[2], exactly like the real call.
 */
function fakeNodeBinary(string $phpBody): string
{
    $path = (string) tempnam(sys_get_temp_dir(), 'laradocs-fake-node-');
    file_put_contents($path, "#!/usr/bin/env php\n<?php\n" . $phpBody);
    chmod($path, 0755);

    return $path;
}

it('interpolates known variables and blanks unknown ones', function () {
    app(Laradocs::class)->share('product', 'Laradocs');

    expect(render('Welcome to {{ product }}.'))->toContain('Welcome to Laradocs.')
        ->and(render('Hello {{ missing }}!'))->toContain('Hello !');
});

it('leaves variables inside code untouched', function () {
    app(Laradocs::class)->share('product', 'Laradocs');

    $html = render("Use `{{ product }}` literally.\n\n```\n{{ product }}\n```");

    expect($html)->toContain('{{ product }}');
});

it('renders a registered macro with named arguments', function () {
    $html = render("@docs('alert', type: 'warning', body: 'Careful now')");

    expect($html)->toContain('laradocs-alert-warning')
        ->and($html)->toContain('Careful now');
});

it('converts github style callouts', function () {
    $html = render("> [!NOTE]\n> Remember this.");

    expect($html)->toContain('laradocs-callout laradocs-callout-note')
        ->and($html)->toContain('Remember this.');
});

it('adds ids and anchors to headings', function () {
    $html = render('## Getting Started');

    expect($html)->toContain('id="getting-started"')
        ->and($html)->toContain('laradocs-anchor');
});

it('wraps code blocks with a language label and copy button', function () {
    $html = render("```php\n echo 1;\n```");

    expect($html)->toContain('laradocs-code')
        ->and($html)->toContain('data-language="php"')
        ->and($html)->toContain('laradocs-code-copy');
});

it('enhances images with lazy loading and captions', function () {
    $html = render('![Alt text](/img/x.png "A caption")');

    expect($html)->toContain('loading="lazy"')
        ->and($html)->toContain('<figcaption>A caption</figcaption>');
});

it('converts local video and youtube links to players', function () {
    $video = render('![clip](/media/demo.mp4)');
    $youtube = render('[watch](https://youtu.be/abc123)');

    expect($video)->toContain('<video')
        ->and($video)->toContain('video/mp4')
        ->and($youtube)->toContain('youtube-nocookie.com/embed/abc123');
});

it('renders mermaid blocks as diagrams with a no-js fallback and lazy loader', function () {
    $html = render("```mermaid\ngraph TD; A-->B;\n```");

    expect($html)->toContain('class="laradocs-mermaid"')
        ->and($html)->toContain('laradocs-mermaid-source')
        // The graph source survives as the no-JS fallback.
        ->and($html)->toContain('graph TD; A--&gt;B;')
        // It is not dressed up as a copyable code block.
        ->and($html)->not->toContain('laradocs-code-copy')
        // The lazy loader is appended only because a diagram is present.
        ->and($html)->toContain('<script type="module">')
        ->and($html)->toContain('mermaid.esm.min.mjs');
});

it('only injects the mermaid loader once per page', function () {
    $html = render("```mermaid\ngraph TD; A-->B;\n```\n\n```mermaid\ngraph LR; C-->D;\n```");

    expect(substr_count($html, '<script type="module">'))->toBe(1)
        ->and(substr_count($html, 'class="laradocs-mermaid"'))->toBe(2);
});

it('leaves ordinary code blocks untouched by the mermaid extension', function () {
    $html = render("```php\necho 1;\n```");

    expect($html)->toContain('laradocs-code-copy')
        ->and($html)->not->toContain('laradocs-mermaid');
});

it('falls back to a plain code block when mermaid is disabled', function () {
    config()->set('laradocs.parser.extensions.mermaid', false);
    app()->forgetInstance(DocumentParser::class);

    $html = render("```mermaid\ngraph TD; A-->B;\n```");

    expect($html)->not->toContain('laradocs-mermaid')
        ->and($html)->not->toContain('mermaid.esm.min.mjs')
        // Without the extension it is just a normal fenced code block.
        ->and($html)->toContain('laradocs-code');
});

// ── KaTeX math ───────────────────────────────────────────────────────────────

it('renders block math as a katex wrapper with a no-js fallback', function () {
    $html = render("$$\n\\frac{a}{b}\n$$");

    expect($html)->toContain('class="laradocs-katex-block"')
        ->and($html)->toContain('data-laradocs-katex="block"')
        // The raw expression is the text content and the data-expr attribute.
        ->and($html)->toContain('data-expr="\\frac{a}{b}"')
        // The bootstrap script is injected.
        ->and($html)->toContain('<script>')
        ->and($html)->toContain('katex.min.js');
});

it('renders inline math as a katex span', function () {
    $html = render('The formula $E = mc^2$ is famous.');

    expect($html)->toContain('class="laradocs-katex-inline"')
        ->and($html)->toContain('data-laradocs-katex="inline"')
        ->and($html)->toContain('data-expr="E = mc^2"');
});

it('keeps an escaped dollar inside inline math instead of closing it', function () {
    $html = render('Costs $\$5 \neq \$6$ today.');

    // The whole "\$5 \neq \$6" is one expression; the escaped dollars do not
    // terminate it early.
    expect($html)->toContain('class="laradocs-katex-inline"')
        ->and($html)->toContain('data-expr="\\$5 \neq \\$6"')
        ->and(substr_count($html, 'data-laradocs-katex="inline"'))->toBe(1);
});

it('renders single-line display math $$…$$ as a block span', function () {
    $html = render('Center this: $$\\frac{a}{b}$$ done.');

    expect($html)->toContain('class="laradocs-katex-block"')
        ->and($html)->toContain('data-laradocs-katex="block"');
});

it('injects the katex bootstrap script only once per page', function () {
    $html = render("$$\n\\alpha\n$$\n\nAnd also $\\beta$ inline.");

    expect(substr_count($html, '<script>'))->toBe(1)
        ->and($html)->toContain('data-laradocs-katex="block"')
        ->and($html)->toContain('data-laradocs-katex="inline"');
});

it('HTML-encodes math expressions containing special characters', function () {
    $html = render('Is $a < b$ true?');

    expect($html)->toContain('data-expr="a &lt; b"')
        ->and($html)->toContain('class="laradocs-katex-inline"');
});

it('leaves math inside fenced code blocks untouched', function () {
    $html = render("```\n\$\$\n\\frac{a}{b}\n\$\$\n```");

    expect($html)->not->toContain('data-laradocs-katex')
        ->and($html)->toContain('laradocs-code');
});

it('leaves math inside inline code untouched', function () {
    $html = render('Use `$x$` for variables.');

    expect($html)->not->toContain('data-laradocs-katex')
        ->and($html)->toContain('$x$');
});

it('uses the configured js and css urls in the bootstrap script', function () {
    $ext = new KatexExtension(
        'https://example.com/katex.js',
        'https://example.com/katex.css',
    );

    // Simulate what processMarkdown + CommonMark produce for $x^2$
    $placeholder = '<span class="laradocs-katex-inline" data-laradocs-katex="inline" data-expr="x^2">x^2</span>';
    $result = $ext->processHtml("<p>$placeholder</p>");

    expect($result)->toContain('https://example.com/katex.css')
        ->and($result)->toContain('https://example.com/katex.js');
});

it('restores an unclosed $$ block verbatim instead of dropping it', function () {
    $html = render("$$\n\\frac{a}{b}");

    expect($html)->not->toContain('data-laradocs-katex')
        ->and($html)->toContain('frac{a}{b}');
});

it('server-side renders math through the node binary when SSR is enabled', function () {
    $node = fakeNodeBinary(<<<'PHP'
        $batch = json_decode($argv[2] ?? '[]', true) ?: [];
        echo json_encode(array_map(
            static fn ($e) => '<span class="katex-ssr" data-mode="'.($e['display'] ? 'block' : 'inline').'">'.$e['expr'].'</span>',
            $batch,
        ));
        PHP);

    $ext = new KatexExtension(
        'https://example.com/katex.js',
        'https://example.com/katex.css',
        ssr: true,
        nodeBin: $node,
    );

    $html = $ext->processHtml($ext->processMarkdown("Inline \$x^2\$ and:\n\n\$\$\n\\frac{a}{b}\n\$\$"));

    // Both expressions are pre-rendered into the wrapper and marked so the
    // client-side script skips them; the stylesheet is still injected.
    expect($html)->toContain('class="katex-ssr"')
        ->and($html)->toContain('data-mode="inline"')
        ->and($html)->toContain('data-mode="block"')
        ->and(substr_count($html, 'data-katex-rendered="1"'))->toBe(2)
        ->and($html)->toContain('https://example.com/katex.css');
});

it('falls back to client-side rendering when the node renderer exits non-zero', function () {
    $node = fakeNodeBinary("fwrite(STDERR, 'boom');\nexit(1);");

    $ext = new KatexExtension(
        'https://example.com/katex.js',
        'https://example.com/katex.css',
        ssr: true,
        nodeBin: $node,
    );

    $html = $ext->processHtml($ext->processMarkdown("\$\$\n\\frac{a}{b}\n\$\$"));

    // Nothing is pre-rendered; the wrapper and the client bootstrap survive.
    expect($html)->not->toContain('data-katex-rendered="1"')
        ->and($html)->toContain('data-laradocs-katex="block"')
        ->and($html)->toContain('https://example.com/katex.js');
});

it('falls back to client-side rendering when the node renderer emits invalid json', function () {
    $node = fakeNodeBinary("echo 'not json at all';");

    $ext = new KatexExtension(
        'https://example.com/katex.js',
        'https://example.com/katex.css',
        ssr: true,
        nodeBin: $node,
    );

    $html = $ext->processHtml($ext->processMarkdown("\$\$\n\\frac{a}{b}\n\$\$"));

    expect($html)->not->toContain('data-katex-rendered="1"')
        ->and($html)->toContain('data-laradocs-katex="block"');
});

// Disable test last — it leaves katex=false in shared config state.
it('falls back to plain text when katex is disabled', function () {
    config()->set('laradocs.parser.extensions.katex', false);
    app()->forgetInstance(DocumentParser::class);

    $html = render(<<<'MD'
$$
\frac{a}{b}
$$

And $x$ inline.
MD);

    expect($html)->not->toContain('data-laradocs-katex')
        ->and($html)->not->toContain('katex.min.js');
});

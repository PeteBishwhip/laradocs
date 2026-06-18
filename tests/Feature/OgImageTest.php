<?php

declare(strict_types=1);

use Intervention\Image\Exceptions\InvalidArgumentException;
use Laradocs\Contracts\OgImageGenerator;
use Laradocs\Routing\DocumentUrl;
use Laradocs\Seo\OgImageData;
use Laradocs\Seo\TheOgImageGenerator;

/**
 * A counting stand-in so tests can assert the generator is invoked (and how
 * often) without paying for a real render.
 */
function fakeOgGenerator(string $bytes = 'FAKE-PNG-BYTES'): OgImageGenerator
{
    return new class($bytes) implements OgImageGenerator
    {
        public int $calls = 0;

        public function __construct(public string $bytes) {}

        public function generate(OgImageData $data): string
        {
            $this->calls++;

            return $this->bytes;
        }
    };
}

beforeEach(function () {
    config()->set('laradocs.ui.brand.title', 'Acme Docs');

    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\norder: 1\n---\n# Welcome\n\nThe home page.\n",
        'guide/intro.md' => "---\ntitle: Intro\ndescription: All about the intro.\n---\n## Step one\n",
        'guide/imaged.md' => "---\ntitle: Imaged\nimage: https://example.com/page.png\n---\nContent.\n",
    ]);
});

it('renders a PNG from the default the-og generator', function () {
    $bytes = (new TheOgImageGenerator)->generate(OgImageData::forPage('Hello', 'A description'));

    // PNG magic number — proves the-og rendered a real image in this environment.
    expect(substr($bytes, 0, 8))->toBe("\x89PNG\r\n\x1a\n");
});

it('points og:image at the generated route when no image is declared', function () {
    $html = $this->get('/docs/guide/intro')->assertOk()->getContent();

    expect($html)
        ->toContain('property="og:image"')
        ->toContain('/docs/_laradocs/og/guide/intro');
});

it('serves a generated card as image/png for a document', function () {
    $this->get('/docs/_laradocs/og/guide/intro')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('serves a generated card for the landing page', function () {
    $this->get('/docs/_laradocs/og')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('404s when the page does not exist', function () {
    $this->get('/docs/_laradocs/og/does/not/exist')->assertNotFound();
});

it('redirects to a front-matter image instead of generating (front-matter trumps generation)', function () {
    $this->app->instance(OgImageGenerator::class, $generator = fakeOgGenerator());

    $this->get('/docs/_laradocs/og/guide/imaged')
        ->assertRedirect('https://example.com/page.png');

    expect($generator->calls)->toBe(0);
});

it('redirects to a site-wide seo.image instead of generating', function () {
    config()->set('laradocs.seo.image', 'https://example.com/site.png');

    $this->get('/docs/_laradocs/og/guide/intro')
        ->assertRedirect('https://example.com/site.png');
});

it('uses a custom OgImageGenerator binding', function () {
    $this->app->instance(OgImageGenerator::class, fakeOgGenerator('CUSTOM-BYTES'));

    $response = $this->get('/docs/_laradocs/og/guide/intro')->assertOk();

    expect($response->getContent())->toBe('CUSTOM-BYTES');
});

it('caches a generated card so it renders once', function () {
    config()->set('laradocs.cache.enabled', true);
    $this->app->instance(OgImageGenerator::class, $generator = fakeOgGenerator());

    $this->get('/docs/_laradocs/og/guide/intro')->assertOk();
    $this->get('/docs/_laradocs/og/guide/intro')->assertOk();

    expect($generator->calls)->toBe(1);
});

it('404s the og route when generation is disabled at runtime', function () {
    config()->set('laradocs.seo.og_image.enabled', false);

    $this->get('/docs/_laradocs/og/guide/intro')->assertNotFound();
});

it('omits the generated og:image url when generation is disabled', function () {
    config()->set('laradocs.seo.og_image.enabled', false);

    expect(DocumentUrl::ogImage('guide/intro'))->toBeNull();
});

it('builds an absolute og:image url for a slug when enabled', function () {
    expect(DocumentUrl::ogImage('guide/intro'))
        ->toContain('/docs/_laradocs/og/guide/intro');

    expect(DocumentUrl::ogImage(''))->toContain('/docs/_laradocs/og');
});

it('returns null when the og route is not registered', function () {
    // A consumer owning its own docs URLs may not wire an og route under the
    // configured name prefix; the helper degrades gracefully rather than 500.
    config()->set('laradocs.route.name', 'missing.');

    expect(DocumentUrl::ogImage('guide/intro'))->toBeNull();
});

it('generates a card for the landing page when there is no home document', function () {
    config()->set('laradocs.seo.description', 'A great place to start.');
    config()->set('laradocs.docs.path', '/definitely/not/here');

    $this->get('/docs/_laradocs/og')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('versions the generated og image url and resolves it back to a document', function () {
    config()->set('laradocs.versions.enabled', true);
    config()->set('laradocs.versions.available', null);

    $this->makeDocs([
        'v2/index.md' => "---\ntitle: Home\n---\n# V2\n",
        'v2/getting-started.md' => "---\ntitle: Start\n---\n# V2 Start\n",
    ]);

    // The og:image meta carries the active version handle...
    $this->get('/docs/v2/getting-started')
        ->assertOk()
        ->assertSee('/docs/_laradocs/og/v2/getting-started', false);

    // ...and the og route strips that handle to resolve the same document.
    $this->get('/docs/_laradocs/og/v2/getting-started')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

describe('TheOgImageGenerator', function () {
    it('renders a card with a background colour and no description or url', function () {
        $bytes = (new TheOgImageGenerator)->generate(
            new OgImageData(title: 'Title only', backgroundColor: '#101010'),
        );

        expect(substr($bytes, 0, 8))->toBe("\x89PNG\r\n\x1a\n");
    });

    it('falls back to a logo-less card when the logo is unreadable', function () {
        $bytes = (new TheOgImageGenerator)->generate(
            new OgImageData(title: 'Branded', logo: '/no/such/logo.png'),
        );

        expect(substr($bytes, 0, 8))->toBe("\x89PNG\r\n\x1a\n");
    });

    it('rethrows when rendering fails for a reason other than the logo', function () {
        expect(fn () => (new TheOgImageGenerator)->generate(
            new OgImageData(title: 'Bad', accentColor: 'not-a-real-colour'),
        ))->toThrow(InvalidArgumentException::class);
    });
});

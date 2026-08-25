<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Laradocs\Laradocs;
use Laradocs\Media\MediaSource;

/**
 * Signing is on by default, so a test that fetches media directly has to say
 * that it is testing the unsigned path.
 */
function withoutSigning(): void
{
    config()->set('laradocs.media.signed', false);
}

/**
 * A one-pixel PNG, so the fixtures are real files a browser would accept.
 */
function pixel(): string
{
    return (string) base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    );
}

it('leaves sources alone by default', function () {
    $this->makeDocs([
        'guide.md' => "---\ntitle: Guide\n---\n\n![Diagram](diagram.png)\n",
        'diagram.png' => pixel(),
    ]);

    $html = (string) app(Laradocs::class)->find('guide')?->html;

    expect($html)->toContain('src="diagram.png"');
});

it('serves nothing while the source is public', function () {
    expect(route('laradocs.media', ['path' => 'diagram.png']))->toContain('/docs/_media/diagram.png');

    // A 404 rather than a 403 about a signature: the route serves nothing at
    // all until a source is configured.
    $this->get('/docs/_media/diagram.png')->assertNotFound();
});

it('points a relative source at the media route', function () {
    config()->set('laradocs.media.source', MediaSource::RELATIVE);

    $this->makeDocs([
        'guide/intro.md' => "---\ntitle: Intro\n---\n\n![Diagram](../img/diagram.png)\n",
        'img/diagram.png' => pixel(),
    ]);

    $html = (string) app(Laradocs::class)->find('guide/intro')?->html;

    expect($html)->toContain('/docs/_media/img/diagram.png');
});

it('serves a file that sits beside the markdown', function () {
    withoutSigning();
    config()->set('laradocs.media.source', MediaSource::RELATIVE);

    $this->makeDocs([
        'guide.md' => "---\ntitle: Guide\n---\n",
        'img/diagram.png' => pixel(),
    ]);

    $this->get('/docs/_media/img/diagram.png')
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
});

it('never serves the markdown itself', function () {
    withoutSigning();
    config()->set('laradocs.media.source', MediaSource::RELATIVE);

    $this->makeDocs(['guide.md' => "---\ntitle: Guide\n---\n"]);

    $this->get('/docs/_media/guide.md')->assertNotFound();
});

it('refuses a path that climbs out of the docs directory', function () {
    withoutSigning();
    config()->set('laradocs.media.source', MediaSource::RELATIVE);

    $this->makeDocs(['guide.md' => "---\ntitle: Guide\n---\n"]);

    $this->get('/docs/_media/../../.env')->assertNotFound();
    $this->get('/docs/_media/img/../../../.env')->assertNotFound();
});

it('refuses a file whose contents do not match its extension', function () {
    withoutSigning();
    config()->set('laradocs.media.source', MediaSource::RELATIVE);

    $this->makeDocs([
        'guide.md' => "---\ntitle: Guide\n---\n",
        'img/sneaky.png' => "<?php echo 'not an image';",
    ]);

    $this->get('/docs/_media/img/sneaky.png')->assertNotFound();
});

it('honours a narrowed type list', function () {
    withoutSigning();
    config()->set('laradocs.media.source', MediaSource::RELATIVE);
    config()->set('laradocs.media.types', ['image/png']);

    $this->makeDocs([
        'guide.md' => "---\ntitle: Guide\n---\n\n![A](a.png)\n![B](b.pdf)\n",
        'a.png' => pixel(),
        'b.pdf' => '%PDF-1.4',
    ]);

    $html = (string) app(Laradocs::class)->find('guide')?->html;

    expect($html)->toContain('/docs/_media/a.png')
        ->and($html)->toContain('src="b.pdf"');

    $this->get('/docs/_media/b.pdf')->assertNotFound();
});

it('serves media from a disk', function () {
    withoutSigning();

    Storage::fake('media');
    Storage::disk('media')->put('img/diagram.png', pixel());

    config()->set('laradocs.media.source', MediaSource::DISK);
    config()->set('laradocs.media.disk', 'media');

    $this->makeDocs(['guide.md' => "---\ntitle: Guide\n---\n\n![Diagram](img/diagram.png)\n"]);

    expect((string) app(Laradocs::class)->find('guide')?->html)
        ->toContain('/docs/_media/img/diagram.png');

    $this->get('/docs/_media/img/diagram.png')->assertOk();
});

it('leaves remote and rooted sources untouched', function () {
    config()->set('laradocs.media.source', MediaSource::RELATIVE);

    $this->makeDocs([
        'guide.md' => "---\ntitle: Guide\n---\n\n![A](https://example.com/a.png)\n![B](/img/b.png)\n",
    ]);

    $html = (string) app(Laradocs::class)->find('guide')?->html;

    expect($html)->toContain('https://example.com/a.png')
        ->and($html)->toContain('src="/img/b.png"');
});

it('signs media urls by default', function () {
    config()->set('laradocs.media.source', MediaSource::RELATIVE);

    $this->makeDocs([
        'guide.md' => "---\ntitle: Guide\n---\n\n![Diagram](img/diagram.png)\n",
        'img/diagram.png' => pixel(),
    ]);

    expect((string) app(Laradocs::class)->find('guide')?->html)->toContain('signature=');
});

it('keeps a signature stable between renders when no ttl is set', function () {
    config()->set('laradocs.media.source', MediaSource::RELATIVE);

    $this->makeDocs([
        'guide.md' => "---\ntitle: Guide\n---\n\n![Diagram](img/diagram.png)\n",
        'img/diagram.png' => pixel(),
    ]);

    // A URL that does not change between renders is one a browser can cache.
    $first = (string) app(Laradocs::class)->find('guide')?->html;
    $second = (string) app(Laradocs::class)->find('guide')?->html;

    expect($first)->toBe($second);
});

it('signs media urls without ever caching a signature', function () {
    config()->set('laradocs.media.source', MediaSource::RELATIVE);
    config()->set('laradocs.media.signed', true);
    config()->set('laradocs.cache.enabled', true);

    $this->makeDocs([
        'guide.md' => "---\ntitle: Guide\n---\n\n![Diagram](img/diagram.png)\n",
        'img/diagram.png' => pixel(),
    ]);

    $first = (string) app(Laradocs::class)->find('guide')?->html;

    expect($first)->toContain('signature=');

    // Rendered again from the cache, the signature is produced afresh rather
    // than served from the entry every reader shares.
    config()->set('app.key', 'base64:' . base64_encode(random_bytes(32)));

    $second = (string) app(Laradocs::class)->find('guide')?->html;

    expect($second)->toContain('signature=')
        ->and($second)->not->toBe($first);
});

it('refuses an unsigned request when signing is on', function () {
    config()->set('laradocs.media.source', MediaSource::RELATIVE);

    $this->makeDocs([
        'guide.md' => "---\ntitle: Guide\n---\n\n![Diagram](img/diagram.png)\n",
        'img/diagram.png' => pixel(),
    ]);

    $this->get('/docs/_media/img/diagram.png')->assertForbidden();

    preg_match('#(/docs/_media/[^"]+)#', (string) app(Laradocs::class)->find('guide')?->html, $matches);

    $this->get(html_entity_decode($matches[1]))->assertOk();
});

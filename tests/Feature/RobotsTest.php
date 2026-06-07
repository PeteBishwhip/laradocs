<?php

declare(strict_types=1);

use Illuminate\Contracts\Routing\Registrar;
use Laradocs\Routing\DocumentRouter;

it('serves robots.txt with a plain text content type', function () {
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $response = $this->get('/docs/robots.txt');

    $response->assertOk();
    expect((string) $response->headers->get('Content-Type'))->toStartWith('text/plain');
});

it('points crawlers at the sitemap by default', function () {
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $body = $this->get('/docs/robots.txt')->getContent();

    expect($body)->toContain("User-agent: *\nAllow: /")
        ->and($body)->toContain('Sitemap: ' . url('/docs/sitemap.xml'));
});

it('replaces the body with a disallow-all when docs are disabled', function () {
    config()->set('laradocs.enabled', false);
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    $response = $this->get('/docs/robots.txt')->assertOk();
    $body = $response->getContent();

    expect($body)->toContain("User-agent: *\nDisallow: /")
        ->and($body)->not->toContain('Sitemap:')
        ->and($body)->not->toContain('Allow:');
});

it('emits custom user-agent rules from config', function () {
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    config()->set('laradocs.robots.rules', [
        [
            'user_agent' => 'GPTBot',
            'disallow' => ['/'],
        ],
        [
            'user_agent' => '*',
            'allow' => ['/'],
            'disallow' => ['/_laradocs/'],
        ],
    ]);

    $body = $this->get('/docs/robots.txt')->getContent();

    expect($body)->toContain("User-agent: GPTBot\nDisallow: /")
        ->and($body)->toContain("User-agent: *\nDisallow: /_laradocs/\nAllow: /")
        ->and($body)->toContain('Sitemap: ' . url('/docs/sitemap.xml'));
});

it('supports multiple user agents per rule block', function () {
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    config()->set('laradocs.robots.rules', [
        [
            'user_agent' => ['GPTBot', 'CCBot'],
            'disallow' => '/',
        ],
    ]);

    $body = $this->get('/docs/robots.txt')->getContent();

    expect($body)->toContain("User-agent: GPTBot\nUser-agent: CCBot\nDisallow: /");
});

it('ignores malformed entries and missing user agents in the rules array', function () {
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    config()->set('laradocs.robots.rules', [
        'not-an-array',
        [
            'user_agent' => 42,
            'disallow' => ['/internal/'],
            'allow' => 7,
        ],
    ]);

    $body = $this->get('/docs/robots.txt')->getContent();

    expect($body)->toContain("User-agent: *\nDisallow: /internal/");
});

it('serves robots.txt on a custom route prefix', function () {
    $this->makeDocs(['a.md' => "---\ntitle: A\n---\nbody\n"]);

    (new DocumentRouter)->register(app(Registrar::class), [
        'prefix' => 'manual',
        'name' => 'manual.',
        'middleware' => ['web'],
    ]);

    $body = $this->get('/manual/robots.txt')->assertOk()->getContent();

    expect($body)->toContain('Sitemap:');
});

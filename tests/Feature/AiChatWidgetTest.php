<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Laradocs\Ai\ChatService;

/**
 * The widget: what the layout renders on a docs page, what the component
 * renders anywhere else, and the two assets they load.
 */
beforeEach(function (): void {
    if (! ChatService::installed()) {
        $this->markTestSkipped('laravel/ai is not installed.');
    }

    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n# Home\n",
        'install.md' => "---\ntitle: Installing\n---\n# Installing\n",
    ]);
});

it('leaves the docs page untouched while the assistant is off', function (): void {
    $html = $this->get('/docs/install')->assertOk()->getContent();

    expect($html)->not->toContain('data-laradocs-ai')
        ->and($html)->not->toContain('laradocs-ai.js')
        ->and($html)->not->toContain('laradocs-ai.css');
});

it('renders the widget and its assets on a docs page', function (): void {
    config()->set('laradocs.ai.enabled', true);

    $html = $this->get('/docs/install')->assertOk()->getContent();

    expect($html)->toContain('data-laradocs-ai')
        ->and($html)->toContain('_laradocs/asset/laradocs-ai.css')
        ->and($html)->toContain('_laradocs/asset/laradocs-ai.js')
        ->and($html)->toContain('_laradocs/ai/chat')
        ->and($html)->toContain('data-laradocs-ai-token');
});

it('keeps the widget off the page when only the widget is switched off', function (): void {
    config()->set('laradocs.ai.enabled', true);
    config()->set('laradocs.ai.widget.enabled', false);

    $html = $this->get('/docs/install')->assertOk()->getContent();

    expect($html)->not->toContain('data-laradocs-ai')
        ->and($html)->not->toContain('laradocs-ai.js');
});

it('tells the widget how the endpoint will answer', function (): void {
    config()->set('laradocs.ai.enabled', true);
    config()->set('laradocs.ai.stream', false);
    config()->set('laradocs.ai.history', 4);
    config()->set('laradocs.ai.max_chars', 500);
    config()->set('laradocs.ai.widget.position', 'left');
    config()->set('laradocs.ai.widget.greeting', 'Hello from the config.');

    $html = $this->get('/docs/install')->assertOk()->getContent();

    expect($html)->toContain('data-laradocs-ai-stream="0"')
        ->and($html)->toContain('data-laradocs-ai-history="4"')
        ->and($html)->toContain('data-laradocs-ai-max="500"')
        ->and($html)->toContain('data-position="left"')
        ->and($html)->toContain('Hello from the config.');
});

it('scopes the widget to the version and language the reader is on', function (): void {
    config()->set('laradocs.ai.enabled', true);
    config()->set('laradocs.versions.enabled', true);
    config()->set('laradocs.locale.available', ['en' => 'English', 'fr' => 'French']);
    $this->makeDocs([
        'v2/install.md' => "---\ntitle: Installing\n---\n# v2\n",
    ]);

    $html = $this->get('/docs/fr/v2/install')->assertOk()->getContent();

    expect($html)->toContain('data-laradocs-ai-version="v2"')
        ->and($html)->toContain('lang=fr');
});

it('renders the widget anywhere through the blade component', function (): void {
    config()->set('laradocs.ai.enabled', true);

    $html = Blade::render('<x-laradocs::ai-chat />');

    expect($html)->toContain('data-laradocs-ai')
        ->and($html)->toContain('_laradocs/asset/laradocs-ai.css')
        ->and($html)->toContain('_laradocs/asset/laradocs-ai.js');
});

it('leaves the assets out of the component on request', function (): void {
    config()->set('laradocs.ai.enabled', true);

    $html = Blade::render('<x-laradocs::ai-chat :assets="false" />');

    expect($html)->toContain('data-laradocs-ai')
        ->and($html)->not->toContain('laradocs-ai.css')
        ->and($html)->not->toContain('laradocs-ai.js');
});

it('renders nothing from the component while the assistant is off', function (): void {
    expect(trim(Blade::render('<x-laradocs::ai-chat />')))->toBe('');
});

it('serves the widget assets', function (string $file, string $type): void {
    $response = $this->get('/docs/_laradocs/asset/' . $file)->assertOk();

    expect($response->headers->get('Content-Type'))->toContain($type)
        ->and($response->getContent())->toContain('laradocs-ai');
})->with([
    'stylesheet' => ['laradocs-ai.css', 'text/css'],
    'script' => ['laradocs-ai.js', 'javascript'],
]);

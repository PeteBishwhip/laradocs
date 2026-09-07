<?php

declare(strict_types=1);

use Laradocs\Ai\ChatService;

/**
 * The AI chat without the Laravel AI SDK behind it.
 *
 * laravel/ai needs Laravel 12 or newer, so the Laravel 11 legs of the test
 * matrix run without it and exercise this file. Everywhere else the package is
 * installed and these paths are unreachable, so they skip instead.
 */
beforeEach(function (): void {
    if (ChatService::installed()) {
        $this->markTestSkipped('laravel/ai is installed, so the missing-package path is unreachable.');
    }

    config()->set('laradocs.ai.enabled', true);
    $this->makeDocs(['_index.md' => "---\ntitle: Home\n---\n# Home\n"]);
});

it('404s the chat endpoint, even switched on', function (): void {
    $this->postJson('/docs/_laradocs/ai/chat', ['message' => 'hello'])->assertNotFound();
});

it('reports itself unavailable rather than half working', function (): void {
    expect(app(ChatService::class)->available())->toBeFalse();
});

it('leaves the widget off the page', function (): void {
    expect($this->get('/docs')->getContent())->not->toContain('data-laradocs-ai');
});

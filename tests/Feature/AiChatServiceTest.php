<?php

declare(strict_types=1);

use Laradocs\Ai\ChatExchange;
use Laradocs\Ai\ChatMessage;
use Laradocs\Ai\ChatRequest;
use Laradocs\Ai\ChatService;
use Laradocs\Ai\DocsAgent;
use Laradocs\Contracts\DocumentVisibility;
use Laradocs\Documents\Document;
use Laradocs\Documents\DocumentCollection;
use Laradocs\Exceptions\AiChatUnavailableException;
use Laradocs\Facades\Laradocs;
use Laradocs\Mcp\Tools\ListPagesTool;
use Laradocs\Mcp\Tools\SearchDocsTool;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\TextResponse;
use Laravel\Mcp\Request as McpRequest;

/**
 * The PHP entry point: assembling the agent, answering, and handing the
 * finished exchange to whatever a host application registered.
 *
 * laravel/ai is an optional dependency (it needs Laravel 12 or newer, so the
 * Laravel 11 test matrix legs run without it), so the whole file skips rather
 * than fails when it is absent. Every provider call goes through the SDK's own
 * fake gateway, so nothing here reaches a real model.
 */
beforeEach(function (): void {
    if (! ChatService::installed()) {
        $this->markTestSkipped('laravel/ai is not installed.');
    }

    config()->set('laradocs.ai.enabled', true);

    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n# Home\n",
        'install.md' => "---\ntitle: Installing\n---\n# Installing\nRun composer require.\n",
    ]);
});

it('knows when it can answer', function (): void {
    $chat = app(ChatService::class);

    expect($chat->available())->toBeTrue();

    config()->set('laradocs.ai.enabled', false);
    expect($chat->available())->toBeFalse();
});

it('refuses to answer while it is switched off', function (): void {
    config()->set('laradocs.ai.enabled', false);

    app(ChatService::class)->ask(new ChatRequest('How do I install?'));
})->throws(AiChatUnavailableException::class, 'LARADOCS_AI=true');

it('answers a question and returns the exchange', function (): void {
    DocsAgent::fake(['Run composer require petebishwhip/laradocs.']);

    $exchange = app(ChatService::class)->ask(new ChatRequest('How do I install?'));

    expect($exchange->answer)->toBe('Run composer require petebishwhip/laradocs.')
        ->and($exchange->streamed)->toBeFalse()
        ->and($exchange->invocationId)->not->toBeNull()
        ->and($exchange->provider)->not->toBeNull();
});

it('hands the exchange to a registered handler, token usage included', function (): void {
    DocsAgent::fake([
        new TextResponse('With composer.', new Usage(promptTokens: 300, completionTokens: 40), new Meta('anthropic', 'claude-sonnet-5')),
    ]);

    $seen = null;
    Laradocs::onChat(function (ChatExchange $exchange) use (&$seen): void {
        $seen = $exchange;
    });

    app(ChatService::class)->ask(new ChatRequest('How do I install?'));

    expect($seen)->toBeInstanceOf(ChatExchange::class)
        ->and($seen->usage->promptTokens)->toBe(300)
        ->and($seen->usage->completionTokens)->toBe(40)
        ->and($seen->usage->total())->toBe(340)
        ->and($seen->model)->toBe('claude-sonnet-5')
        ->and($seen->provider)->toBe('anthropic');
});

it('hands a streamed exchange over once the stream has finished', function (): void {
    DocsAgent::fake(['Streamed answer here.']);

    $seen = null;
    Laradocs::onChat(function (ChatExchange $exchange) use (&$seen): void {
        $seen = $exchange;
    });

    $response = app(ChatService::class)->stream(new ChatRequest('How do I install?'));

    expect($seen)->toBeNull();

    $events = [];
    foreach ($response as $event) {
        $events[] = $event->type();
    }

    expect($events)->toContain('text_delta')
        ->and($seen)->toBeInstanceOf(ChatExchange::class)
        ->and($seen->answer)->toBe('Streamed answer here.')
        ->and($seen->streamed)->toBeTrue();
});

it('reports the tools the assistant reached for', function (): void {
    DocsAgent::fake([
        new ToolCall('call-1', 'search_docs', ['query' => 'install']),
        'You install it with composer.',
    ]);

    $exchange = app(ChatService::class)->ask(new ChatRequest('How do I install?'));

    expect($exchange->tools)->toBe(['search_docs'])
        ->and($exchange->answer)->toBe('You install it with composer.');
});

it('gives the assistant the documentation tools', function (): void {
    $agent = app(ChatService::class)->agent(new ChatRequest('How do I install?'));
    $names = array_map(fn (object $tool): string => $tool->name(), [...$agent->tools()]);

    expect($names)->toBe(['search_docs', 'list_pages', 'fetch_page']);
});

it('withholds the documentation tools when they are switched off', function (): void {
    config()->set('laradocs.ai.mcp.laradocs', false);

    expect([...app(ChatService::class)->agent(new ChatRequest('q'))->tools()])->toBe([]);
});

it('adds the tools a registered resolver hands over', function (): void {
    config()->set('laradocs.ai.mcp.laradocs', false);

    $extra = app(SearchDocsTool::class);
    Laradocs::chatTools(fn (ChatRequest $request): array => $request->question === 'q' ? [$extra] : []);

    expect([...app(ChatService::class)->agent(new ChatRequest('q'))->tools()])->toBe([$extra]);
});

it('appends registered context to the assistant instructions', function (): void {
    Laradocs::chatContext(fn (ChatRequest $request): string => 'The reader is called ' . ($request->user->name ?? 'nobody') . '.');

    $user = new stdClass;
    $user->name = 'Ada';

    $agent = app(ChatService::class)->agent(new ChatRequest('q', [], null, null, $user));

    expect($agent->instructions())->toContain('The reader is called Ada.');
});

it('replays the prior turns as the sdk message types', function (): void {
    $agent = app(ChatService::class)->agent(new ChatRequest('and then?', [
        ChatMessage::user('How do I install?'),
        ChatMessage::assistant('With composer.'),
    ]));

    $thread = [...$agent->messages()];

    expect($thread)->toHaveCount(2)
        ->and($thread[0])->toBeInstanceOf(UserMessage::class)
        ->and($thread[1])->toBeInstanceOf(AssistantMessage::class)
        ->and($thread[1]->content)->toBe('With composer.');
});

it('carries the configured provider, model and timeout onto the agent', function (): void {
    config()->set('laradocs.ai.provider', 'anthropic');
    config()->set('laradocs.ai.model', 'claude-sonnet-5');
    config()->set('laradocs.ai.timeout', 15);

    $agent = app(ChatService::class)->agent(new ChatRequest('q'));

    expect($agent->provider())->toBe('anthropic')
        ->and($agent->model())->toBe('claude-sonnet-5')
        ->and($agent->timeout())->toBe(15);
});

it('accepts a provider failover chain', function (): void {
    config()->set('laradocs.ai.provider', ['anthropic' => 'claude-sonnet-5', 'openai' => null]);

    expect(app(ChatService::class)->agent(new ChatRequest('q'))->provider())
        ->toBe(['anthropic' => 'claude-sonnet-5', 'openai' => null]);
});

it('defers to the sdk default when no provider is configured', function (): void {
    config()->set('laradocs.ai.provider', null);
    config()->set('laradocs.ai.timeout', 0);

    $agent = app(ChatService::class)->agent(new ChatRequest('q'));

    expect($agent->provider())->toBeNull()
        ->and($agent->model())->toBeNull()
        ->and($agent->timeout())->toBe(1);
});

it('reports how the endpoint should answer', function (): void {
    $chat = app(ChatService::class);

    expect($chat->streams())->toBeTrue()
        ->and($chat->historyLimit())->toBe(10);

    config()->set('laradocs.ai.stream', false);
    config()->set('laradocs.ai.history', -4);

    expect($chat->streams())->toBeFalse()
        ->and($chat->historyLimit())->toBe(0);
});

it('answers only from the documents the reader may read', function (): void {
    // The assistant reads through the same loader as every other read path, so
    // a rule that hides a page hides it from the assistant's tools too.
    // Asserted through the tool the assistant would actually call.
    $this->app->bind(DocumentVisibility::class, fn (): DocumentVisibility => new class implements DocumentVisibility
    {
        public function filter(DocumentCollection $documents): DocumentCollection
        {
            return $documents->reject(fn (Document $document): bool => $document->slug === 'install')->values();
        }
    });

    $agent = app(ChatService::class)->agent(new ChatRequest('How do I install?'));

    $listed = json_decode((string) app(ListPagesTool::class)
        ->handle(new McpRequest([]))
        ->content(), true);

    expect(array_column($listed['pages'], 'slug'))->not->toContain('install')
        ->and([...$agent->tools()])->toHaveCount(3);
});

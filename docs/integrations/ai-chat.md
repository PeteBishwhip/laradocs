---
title: AI Chat
description: Answer questions from your documentation with an AI assistant, using any provider the Laravel AI SDK supports.
order: 2
---

# AI Chat

Laradocs can answer questions about your documentation, in a chat panel on your
docs pages or anywhere else in your application. The assistant reads your pages
through tools rather than being trained on them, so it answers from what is
actually written today, and it reads them through the same visibility rules as
every other part of the package, so a restricted page stays restricted.

It is built on the [Laravel AI SDK](https://laravel.com/docs/ai-sdk), which
means every provider that SDK supports is available here: Anthropic, OpenAI,
Gemini, Bedrock, Azure, Mistral, Groq, DeepSeek, Ollama, OpenRouter, xAI and
the rest. Laradocs never asks for an API key of its own. It uses the
credentials already in your `config/ai.php`, so the provider and the token are
always yours.

## Installing

The assistant needs two things: the SDK, and the switch.

```bash
composer require laravel/ai
```

```env
LARADOCS_AI=true
```

Without both, no route is registered and no widget renders, so the feature
costs a site that does not want it precisely nothing.

The SDK needs a provider to talk to. Publish its config and set a key:

```bash
php artisan vendor:publish --tag=ai-config
```

```env
ANTHROPIC_API_KEY=sk-ant-...
```

> [!NOTE]
> The Laravel AI SDK requires Laravel 12 or newer. Laradocs itself still
> supports Laravel 11, so on Laravel 11 every other feature works as before
> and the AI chat is simply unavailable.

Keep `laravel/mcp` installed too. The assistant reads your pages through
Laradocs' own MCP tools, and without them it has no way to open a page:

```bash
composer require laravel/mcp
```

That does **not** expose the public `{prefix}/mcp` endpoint. The tools are
called in process; the [MCP server](/docs/integrations/mcp) is a separate
switch.

## Choosing a provider and model

Leave both unset and the SDK's own `ai.default` decides:

```env
LARADOCS_AI_PROVIDER=anthropic
LARADOCS_AI_MODEL=claude-sonnet-5
```

For a failover chain, publish Laradocs' config and give `provider` an array.
A plain list tries each provider in turn with its default model; a map pins a
model per provider:

```php
'ai' => [
    'provider' => [
        'anthropic' => 'claude-sonnet-5',
        'openai' => null,
    ],
],
```

The first provider that answers wins, so a rate-limited primary degrades to a
secondary rather than to an error.

## The widget

With the assistant enabled, a launcher appears in the corner of every docs
page and opens a chat panel. It streams the answer as it arrives, renders a
small, safe subset of markdown, keeps the thread in the browser, and scopes
every question to the version and language the reader is on.

Tune it from config:

```env
LARADOCS_AI_WIDGET=false                       # keep the endpoint, drop the panel
LARADOCS_AI_WIDGET_POSITION=left               # right (default) | left
LARADOCS_AI_GREETING="Ask me about the API."
```

Everything else the panel says comes from the language files, so a translated
docs site gets a translated assistant. Publish them to reword anything:

```bash
php artisan vendor:publish --tag=laradocs-lang
```

### Off the docs pages

The same panel is a Blade component, so it can go anywhere in your
application: a dashboard, a support page, a marketing site sharing the
application.

```blade
<x-laradocs::ai-chat />
```

It brings its own stylesheet and script, which style and touch nothing but the
widget itself, so it will not restyle the page it lands on. If your own build
already bundles them, leave them out:

```blade
<x-laradocs::ai-chat :assets="false" />
```

The component renders nothing at all while the assistant is switched off, so it
is safe to leave in a layout.

## The endpoint

The widget is a client of a documented endpoint, so anything else can be one
too:

```
POST {prefix}/_laradocs/ai/chat
```

```json
{
  "message": "How do I enable versioning?",
  "history": [
    { "role": "user", "content": "What is Laradocs?" },
    { "role": "assistant", "content": "A documentation package for Laravel." }
  ],
  "version": "v2",
  "stream": false
}
```

| Field | Notes |
|---|---|
| `message` | The question. Required, and capped at `ai.max_chars` characters. |
| `history` | Prior turns, oldest first. Only `user` and `assistant` roles are accepted. Trimmed to the newest `ai.history` turns. |
| `version` | The docs version to answer from. Ignored on a single-version site; an unrecognised handle falls back to the default version. |
| `stream` | `false` asks for one JSON response instead of a stream. |

The endpoint is stateless: the client owns the transcript and replays whatever
it wants remembered. Nothing is stored on your behalf, which is what
[`onChat()`](#recording-what-it-costs) is for.

### Streaming

By default the answer streams back as server-sent events, one frame per event:

```
data: {"type":"stream_start","provider":"anthropic","model":"claude-sonnet-5",...}
data: {"type":"text_delta","delta":"Set ","message_id":"..."}
data: {"type":"text_delta","delta":"`LARADOCS_VERSIONS=true`.","message_id":"..."}
data: {"type":"stream_end","reason":"stop","usage":{...}}
data: [DONE]
```

Behind a proxy that buffers responses, or on a PHP-FPM setup without output
flushing, switch it off and every answer comes back as one JSON body:

```env
LARADOCS_AI_STREAM=false
```

### The JSON response

```json
{
  "question": "How do I enable versioning?",
  "answer": "Set `LARADOCS_VERSIONS=true`...",
  "usage": {
    "prompt_tokens": 1840,
    "completion_tokens": 96,
    "cache_write_tokens": 0,
    "cache_read_tokens": 1536,
    "reasoning_tokens": 0,
    "total_tokens": 3472
  },
  "provider": "anthropic",
  "model": "claude-sonnet-5",
  "invocation_id": "01a07b49-...",
  "tools": ["search_docs", "fetch_page"],
  "streamed": false,
  "locale": "en",
  "version": null
}
```

A provider that fails answers `502` with an `error` and a `message` rather than
a stack trace, because the assistant is a third party service and its bad day
is not your documentation's fault.

### Rate limiting

An answer costs real money, so the endpoint has its own limiter, much tighter
than the [JSON API's](/docs/http-api/search):

```env
LARADOCS_AI_RATE_LIMIT=10   # requests per minute per IP; 0 lifts the limit
```

## Who may ask

The assistant is as open as the docs pages it sits on, which is right for a
public documentation site and wrong for an internal handbook. Three controls
narrow it, checked in this order and each skipped when it is not configured.

**A guard.** An unauthenticated request is refused with a `401`:

```env
LARADOCS_AI_AUTH_GUARD=web
```

**A gate.** A refused request gets a `403`:

```env
LARADOCS_AI_GATE=use-docs-assistant
```

```php
Gate::define('use-docs-assistant', fn (?User $user) => $user?->subscribed());
```

**A callback**, for anything those two cannot express:

```php
use Illuminate\Http\Request;
use Laradocs\Facades\Laradocs;

public function boot(): void
{
    Laradocs::chatAuthorize(fn (Request $request) => $request->user()?->hasVerifiedEmail());
}
```

It is consulted after the guard and the gate, so it narrows access rather than
widening it.

## What the assistant can read

The assistant reads through the same document loader as the navigation,
search, the sitemap and the MCP tools. Bind a
[`DocumentVisibility`](/docs/advanced/visibility) rule and it applies here
without any further work:

```php
use Laradocs\Contracts\DocumentVisibility;
use Laradocs\Documents\DocumentCollection;

class OnlyWhatTheReaderPaidFor implements DocumentVisibility
{
    public function filter(DocumentCollection $documents): DocumentCollection
    {
        if (auth()->user()?->subscribed()) {
            return $documents;
        }

        return $documents->reject(fn ($document) => $document->metadata->get('plan') === 'pro')->values();
    }
}
```

A page the rule hides is not merely left out of the answer: it is invisible to
the tools, so the assistant cannot mention it, quote it, or link to it, and its
system prompt tells it to treat anything it cannot see as non-existent rather
than speculate.

## Tools

### Laradocs' own

Three read-only tools, the same ones the MCP server advertises:

| Tool | What it does |
|---|---|
| `search_docs` | Full-text search across the pages the reader may read. |
| `list_pages` | Enumerate those pages, optionally filtered by group. |
| `fetch_page` | Fetch one page's full markdown and metadata by slug. |

They are on whenever `laravel/mcp` is installed. Turning them off leaves the
assistant with nothing to read, so only do it when you are supplying tools of
your own:

```env
LARADOCS_AI_MCP=false
```

### Your own MCP servers

Point the assistant at MCP servers of your own and their tools join the
built-ins. Both HTTP and local (stdio) servers work:

```php
'ai' => [
    'mcp' => [
        'servers' => [
            'billing' => [
                'url' => env('BILLING_MCP_URL'),
                'token' => env('BILLING_MCP_TOKEN'),
                'headers' => ['X-Tenant' => 'acme'],
                'timeout' => 30,
                'only' => ['lookup_invoice'],
            ],

            'local' => [
                'command' => 'npx',
                'args' => ['-y', '@acme/mcp-server'],
            ],
        ],
    ],
],
```

`only` names the tools to advertise and `except` names the ones to hide;
`only` wins when both are given, and everything is advertised when neither is.

A server that cannot be reached is logged and skipped rather than failing the
answer, so somebody else's outage costs you the tools from that one server and
nothing more.

### Tools in PHP

For a tool the config cannot describe, register a resolver. It is called per
request, so it can build a tool around the reader in front of it:

```php
use Laradocs\Ai\ChatRequest;
use Laradocs\Facades\Laradocs;

Laradocs::chatTools(fn (ChatRequest $request) => $request->user
    ? [new LookUpSubscription($request->user)]
    : []);
```

Return a single tool or anything iterable of tools: a Laravel AI SDK tool, one
of your MCP server's tools, or another agent. They join the documentation
tools rather than replacing them.

## Telling the assistant about the reader

The documentation cannot know which plan somebody is on, which features they
have switched on, or which tenant they belong to. Context resolvers hand the
assistant that, per request, as extra lines appended to its instructions:

```php
use Laradocs\Ai\ChatRequest;
use Laradocs\Facades\Laradocs;

Laradocs::chatContext(function (ChatRequest $request) {
    if ($request->user === null) {
        return null;
    }

    return [
        "The reader is on the {$request->user->plan} plan.",
        "Their account was created on {$request->user->created_at->toDateString()}.",
    ];
});
```

Return a string, anything iterable of strings, or null for nothing. Empty
values are dropped.

To replace the whole system prompt instead of adding to it, publish the config
and set `ai.instructions`. The built-in prompt is what keeps the assistant
answering from your pages rather than from its training, so read it in
`config/laradocs.php` before you replace it.

## Recording what it costs

Every finished exchange is handed to whatever callbacks you registered, with
the question, the answer, the token usage, the provider and model that produced
it, the tools it called, and the reader it answered. This is where a deployment
meters spend, keeps transcripts, or feeds answers into its own analytics:

```php
use Laradocs\Ai\ChatExchange;
use Laradocs\Facades\Laradocs;

public function boot(): void
{
    Laradocs::onChat(function (ChatExchange $exchange) {
        DocsQuestion::create([
            'user_id' => $exchange->user()?->getAuthIdentifier(),
            'question' => $exchange->question(),
            'answer' => $exchange->answer,
            'provider' => $exchange->provider,
            'model' => $exchange->model,
            'prompt_tokens' => $exchange->usage->promptTokens,
            'completion_tokens' => $exchange->usage->completionTokens,
            'total_tokens' => $exchange->usage->total(),
            'tools' => $exchange->tools,
            'streamed' => $exchange->streamed,
        ]);
    });
}
```

Register as many as you need; each is called in turn. Streamed answers are
included, with the answer assembled and the usage totalled across every step,
so token accounting does not depend on whether the reader streamed. A callback
that throws is reported and the rest still run, because by that point the
answer has been produced and, if it streamed, already reached the reader.

`ChatExchange` carries:

| Property | Notes |
|---|---|
| `request` | The `ChatRequest`: question, thread, locale, version and reader. |
| `answer` | The assistant's reply, in markdown. |
| `usage` | A `TokenUsage`, with `total()` across prompt, completion and cache. |
| `provider`, `model` | What actually answered, after any failover. |
| `invocationId` | The SDK's id for the run, for correlating with its own events. |
| `tools` | The tools it called, in order. |
| `streamed` | Whether the answer was streamed. |

## Asking from PHP

The same assistant is available without the HTTP layer, which is what a
scheduled digest, a Slack bot or a support tool would use:

```php
use Laradocs\Ai\ChatMessage;
use Laradocs\Ai\ChatRequest;
use Laradocs\Ai\ChatService;

$exchange = app(ChatService::class)->ask(new ChatRequest(
    question: 'How do I enable versioning?',
    history: [ChatMessage::user('What is Laradocs?')],
    user: $request->user(),
));

echo $exchange->answer;
echo $exchange->usage->total();
```

Registered callbacks fire from here too. Asking while the assistant is
switched off, or without the SDK installed, throws
`Laradocs\Exceptions\AiChatUnavailableException` rather than quietly answering
nothing.

## Testing

The Laravel AI SDK fakes the agent for you, so a test suite never reaches a
real provider:

```php
use Laradocs\Ai\DocsAgent;

it('answers from the docs', function () {
    config()->set('laradocs.ai.enabled', true);

    DocsAgent::fake(['Set LARADOCS_VERSIONS=true.']);

    $this->postJson('/docs/_laradocs/ai/chat', [
        'message' => 'How do I enable versioning?',
        'stream' => false,
    ])->assertOk()->assertJsonPath('answer', 'Set LARADOCS_VERSIONS=true.');

    DocsAgent::assertPromptedTimes(1);
});
```

`DocsAgent::fake()` takes an array of responses, one per step, or a closure for
full control. See the SDK's own testing documentation for the rest.

## Configuration reference

| Key | Env | Default | Notes |
|---|---|---|---|
| `ai.enabled` | `LARADOCS_AI` | `false` | Master switch. |
| `ai.provider` | `LARADOCS_AI_PROVIDER` | `null` | Provider name, or an array for a failover chain. |
| `ai.model` | `LARADOCS_AI_MODEL` | `null` | Model within the provider. |
| `ai.timeout` | `LARADOCS_AI_TIMEOUT` | `60` | Seconds one answer may take. |
| `ai.instructions` | | `null` | Replaces the built-in system prompt. |
| `ai.history` | `LARADOCS_AI_HISTORY` | `10` | Prior turns a client may replay. |
| `ai.max_chars` | `LARADOCS_AI_MAX_CHARS` | `2000` | Longest question accepted. |
| `ai.stream` | `LARADOCS_AI_STREAM` | `true` | Stream answers as server-sent events. |
| `ai.rate_limit` | `LARADOCS_AI_RATE_LIMIT` | `10` | Requests per minute per IP; 0 lifts it. |
| `ai.auth.guard` | `LARADOCS_AI_AUTH_GUARD` | `null` | Guard a reader must satisfy. |
| `ai.auth.gate` | `LARADOCS_AI_GATE` | `null` | Gate ability a reader must pass. |
| `ai.mcp.laradocs` | `LARADOCS_AI_MCP` | `true` | Hand over the documentation tools. |
| `ai.mcp.servers` | | `[]` | Your own MCP servers. |
| `ai.widget.enabled` | `LARADOCS_AI_WIDGET` | `true` | Render the panel on docs pages. |
| `ai.widget.position` | `LARADOCS_AI_WIDGET_POSITION` | `right` | Corner the launcher sits in. |
| `ai.widget.greeting` | `LARADOCS_AI_GREETING` | `null` | Opening message; null uses the translation. |

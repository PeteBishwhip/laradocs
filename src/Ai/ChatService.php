<?php

declare(strict_types=1);

namespace Laradocs\Ai;

use Illuminate\Contracts\Container\Container;
use Laradocs\Contracts\DocumentVisibility;
use Laradocs\Exceptions\AiChatUnavailableException;
use Laradocs\Laradocs;
use Laradocs\Mcp\Tools\FetchPageTool;
use Laradocs\Mcp\Tools\ListPagesTool;
use Laradocs\Mcp\Tools\SearchDocsTool;
use Laradocs\Support\Config;
use Laravel\Ai\Ai;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Laravel\Mcp\Server\Tool;

/**
 * The AI chat, as PHP sees it.
 *
 * Both entry points assemble the same agent and differ only in how the answer
 * comes back: {@see ask()} returns the finished exchange, {@see stream()}
 * returns something that can be handed straight back as a server-sent event
 * response. Either way the callbacks registered with
 * {@see Laradocs::onChat()} see the exchange once it is complete, so token
 * accounting does not depend on which one a deployment uses.
 *
 * Nothing here reads a document itself. The assistant reads through the
 * package's own MCP tools, which go through the configured
 * {@see DocumentVisibility} rule like every other read path, so the reader's
 * authority decides what the assistant can see.
 */
final class ChatService
{
    public function __construct(
        private readonly ChatRegistry $registry,
        private readonly McpServers $servers,
        private readonly Container $container,
    ) {}

    /**
     * Whether the assistant can answer: switched on, and built on a package
     * that is actually installed.
     */
    public function available(): bool
    {
        return Config::bool('laradocs.ai.enabled', false) && self::installed();
    }

    /**
     * Whether the Laravel AI SDK is present.
     */
    public static function installed(): bool
    {
        return class_exists(Ai::class);
    }

    /**
     * Whether answers should be streamed back rather than returned in one
     * response.
     */
    public function streams(): bool
    {
        return Config::bool('laradocs.ai.stream', true);
    }

    /**
     * How many prior turns a client may replay into a request.
     */
    public function historyLimit(): int
    {
        return max(0, Config::int('laradocs.ai.history', 10));
    }

    /**
     * Answer a question, and hand the finished exchange to every registered
     * callback before returning it.
     */
    public function ask(ChatRequest $request): ChatExchange
    {
        $this->ensureAvailable();

        return $this->record(
            $this->agent($request)->prompt($request->question),
            $request,
            streamed: false,
        );
    }

    /**
     * Answer a question as a stream of events. Registered callbacks run once
     * the stream has finished, with the assembled answer and the usage
     * totalled across every step.
     */
    public function stream(ChatRequest $request): StreamableAgentResponse
    {
        $this->ensureAvailable();

        return $this->agent($request)->stream($request->question)->then(
            fn (StreamedAgentResponse $response): ChatExchange => $this->record($response, $request, streamed: true),
        );
    }

    /**
     * Build the agent for a request: its instructions, the thread so far, and
     * the tools it may reach for.
     */
    public function agent(ChatRequest $request): DocsAgent
    {
        return new DocsAgent(
            Instructions::build($request, $this->registry->contextFor($request)),
            $this->thread($request),
            $this->tools($request),
            $this->provider(),
            Config::nullableString('laradocs.ai.model'),
            max(1, Config::int('laradocs.ai.timeout', 60)),
        );
    }

    /**
     * The provider the SDK should answer with, as the config gives it: one
     * provider's name, a failover chain of them, or nothing at all, which
     * leaves the choice to the SDK's own `ai.default`.
     *
     * @return array<array-key, string|null>|string|null
     */
    private function provider(): array|string|null
    {
        $configured = config('laradocs.ai.provider');

        if (is_string($configured)) {
            return $configured;
        }

        if (! is_array($configured)) {
            return null;
        }

        $chain = [];

        foreach ($configured as $name => $model) {
            $chain[$name] = is_string($model) ? $model : null;
        }

        return $chain;
    }

    /**
     * The tools the assistant may call: this package's own documentation
     * tools, then anything configured MCP servers advertise, then anything a
     * registered resolver hands over.
     *
     * @return list<mixed>
     */
    private function tools(ChatRequest $request): array
    {
        $tools = [];

        if (Config::bool('laradocs.ai.mcp.laradocs', true) && class_exists(Tool::class)) {
            $tools[] = $this->container->make(SearchDocsTool::class);
            $tools[] = $this->container->make(ListPagesTool::class);
            $tools[] = $this->container->make(FetchPageTool::class);
        }

        foreach ($this->servers->tools() as $tool) {
            $tools[] = $tool;
        }

        foreach ($this->registry->toolsFor($request) as $tool) {
            $tools[] = $tool;
        }

        return $tools;
    }

    /**
     * The prior turns, in the SDK's own message types.
     *
     * @return list<Message>
     */
    private function thread(ChatRequest $request): array
    {
        return array_map(
            static fn (ChatMessage $message): Message => $message->isUser()
                ? new UserMessage($message->content)
                : new AssistantMessage($message->content),
            $request->history,
        );
    }

    /**
     * Assemble the exchange and hand it to the registered callbacks.
     */
    private function record(AgentResponse $response, ChatRequest $request, bool $streamed): ChatExchange
    {
        $exchange = new ChatExchange(
            $request,
            $response->text,
            TokenUsage::fromArray($response->usage->toArray()),
            $response->meta->provider,
            $response->meta->model,
            $response->invocationId,
            array_values($response->toolCalls
                ->map(static fn (ToolCall $call): string => $call->name)
                ->all()),
            $streamed,
        );

        $this->registry->dispatch($exchange);

        return $exchange;
    }

    private function ensureAvailable(): void
    {
        // @codeCoverageIgnoreStart
        // Unreachable wherever laravel/ai is installed, which is everywhere
        // the coverage suite runs; the endpoint's behaviour without it is
        // covered by AiChatMissingSdkTest, which only runs there.
        if (! self::installed()) {
            throw AiChatUnavailableException::missingPackage();
        }
        // @codeCoverageIgnoreEnd

        if (! Config::bool('laradocs.ai.enabled', false)) {
            throw AiChatUnavailableException::disabled();
        }
    }
}

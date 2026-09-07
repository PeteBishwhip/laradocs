<?php

declare(strict_types=1);

namespace Laradocs\Ai;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Override;

/**
 * The documentation assistant, as the Laravel AI SDK sees it.
 *
 * A concrete agent class rather than one of the SDK's anonymous agents, for
 * two reasons: the SDK keys its test fakes by agent class name, so a named
 * class is what makes `DocsAgent::fake([...])` possible in an application's
 * own test suite; and the provider, model and timeout resolution the SDK does
 * through `provider()` / `model()` / `timeout()` is where this package's
 * configuration meets it.
 *
 * Everything the agent knows is passed in by {@see ChatService}: the
 * instructions, the thread so far, and the tools it may call. It reads no
 * configuration of its own.
 */
final class DocsAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * @param  list<Message>  $thread
     * @param  list<mixed>  $availableTools
     * @param  array<array-key, string|null>|string|null  $configuredProvider
     */
    public function __construct(
        private readonly string $prompt,
        private readonly array $thread = [],
        private readonly array $availableTools = [],
        private readonly array|string|null $configuredProvider = null,
        private readonly ?string $configuredModel = null,
        private readonly int $configuredTimeout = 60,
    ) {}

    #[Override]
    public function instructions(): string
    {
        return $this->prompt;
    }

    /**
     * @return list<Message>
     */
    #[Override]
    public function messages(): iterable
    {
        return $this->thread;
    }

    /**
     * @return list<mixed>
     */
    #[Override]
    public function tools(): iterable
    {
        return $this->availableTools;
    }

    /**
     * The provider, or chain of providers, the SDK should try. Null defers to
     * the SDK's own `ai.default`.
     *
     * @return array<array-key, string|null>|string|null
     */
    public function provider(): array|string|null
    {
        return $this->configuredProvider;
    }

    public function model(): ?string
    {
        return $this->configuredModel;
    }

    public function timeout(): int
    {
        return $this->configuredTimeout;
    }
}

<?php

declare(strict_types=1);

namespace Laradocs\Ai;

use Illuminate\Contracts\Support\Arrayable;
use Laradocs\Laradocs;
use Override;

/**
 * A completed question and answer, with what it cost and what produced it.
 *
 * This is the contract every callback registered with
 * {@see Laradocs::onChat()} receives, and the reason the callback
 * exists: it is the one place a deployment can meter token spend, persist a
 * transcript, or feed an answer into its own analytics without the package
 * taking an opinion on any of those. Callbacks run after the answer has been
 * produced, streamed responses included, and their return value is ignored.
 *
 * @implements Arrayable<string, mixed>
 */
final class ChatExchange implements Arrayable
{
    /**
     * @param  list<string>  $tools  Names of the tools the assistant called,
     *                               in the order it called them.
     */
    public function __construct(
        public readonly ChatRequest $request,
        public readonly string $answer,
        public readonly TokenUsage $usage,
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
        public readonly ?string $invocationId = null,
        public readonly array $tools = [],
        public readonly bool $streamed = false,
    ) {}

    public function question(): string
    {
        return $this->request->question;
    }

    public function user(): ?object
    {
        return $this->request->user;
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(): array
    {
        return [
            'question' => $this->request->question,
            'answer' => $this->answer,
            'usage' => $this->usage->toArray(),
            'provider' => $this->provider,
            'model' => $this->model,
            'invocation_id' => $this->invocationId,
            'tools' => $this->tools,
            'streamed' => $this->streamed,
            'locale' => $this->request->locale,
            'version' => $this->request->version,
        ];
    }
}

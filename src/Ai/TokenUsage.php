<?php

declare(strict_types=1);

namespace Laradocs\Ai;

use Illuminate\Contracts\Support\Arrayable;
use Laradocs\Laradocs;
use Override;

/**
 * What an answer cost, in tokens.
 *
 * A plain value object rather than the SDK's own usage type, so the contract
 * handed to a {@see Laradocs::onChat()} callback stays the same shape
 * whichever provider produced the answer, and stays resolvable in an
 * application that has not installed laravel/ai at all.
 *
 * @implements Arrayable<string, int>
 */
final class TokenUsage implements Arrayable
{
    public function __construct(
        public readonly int $promptTokens = 0,
        public readonly int $completionTokens = 0,
        public readonly int $cacheWriteTokens = 0,
        public readonly int $cacheReadTokens = 0,
        public readonly int $reasoningTokens = 0,
    ) {}

    /**
     * Read the SDK's usage payload, which arrives as the snake_cased array its
     * own value object serialises to.
     *
     * @param  array<array-key, mixed>  $usage
     */
    public static function fromArray(array $usage): self
    {
        return new self(
            self::int($usage, 'prompt_tokens'),
            self::int($usage, 'completion_tokens'),
            self::int($usage, 'cache_write_input_tokens'),
            self::int($usage, 'cache_read_input_tokens'),
            self::int($usage, 'reasoning_tokens'),
        );
    }

    /**
     * Every token the exchange was billed for, cache traffic included.
     */
    public function total(): int
    {
        return $this->promptTokens
            + $this->completionTokens
            + $this->cacheWriteTokens
            + $this->cacheReadTokens;
    }

    /**
     * @return array<string, int>
     */
    #[Override]
    public function toArray(): array
    {
        return [
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'cache_write_tokens' => $this->cacheWriteTokens,
            'cache_read_tokens' => $this->cacheReadTokens,
            'reasoning_tokens' => $this->reasoningTokens,
            'total_tokens' => $this->total(),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $usage
     */
    private static function int(array $usage, string $key): int
    {
        return is_numeric($usage[$key] ?? null) ? (int) $usage[$key] : 0;
    }
}

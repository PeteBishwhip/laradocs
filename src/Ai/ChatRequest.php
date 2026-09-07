<?php

declare(strict_types=1);

namespace Laradocs\Ai;

use Laradocs\Laradocs;

/**
 * A question, plus everything the assistant needs to answer it in the right
 * context: the thread so far, the language and version the reader is looking
 * at, and who they are.
 *
 * Handed to every callback registered with
 * {@see Laradocs::chatContext()} and
 * {@see Laradocs::chatTools()}, which is why it carries the reader
 * rather than leaving callbacks to reach for the auth facade themselves: the
 * assistant may be answering inside a queued job one day, where there is no
 * request to reach into.
 */
final class ChatRequest
{
    /**
     * @param  list<ChatMessage>  $history
     */
    public function __construct(
        public readonly string $question,
        public readonly array $history = [],
        public readonly ?string $locale = null,
        public readonly ?string $version = null,
        public readonly ?object $user = null,
    ) {}
}

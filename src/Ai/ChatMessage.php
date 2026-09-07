<?php

declare(strict_types=1);

namespace Laradocs\Ai;

use Illuminate\Contracts\Support\Arrayable;
use Laradocs\Laradocs;
use Override;

/**
 * One turn of a chat thread, as it travels between the browser and the
 * assistant.
 *
 * The client owns the transcript: every request replays the turns it wants the
 * assistant to remember, and the package never persists one on its behalf.
 * Storing threads is what {@see Laradocs::onChat()} is for.
 *
 * @implements Arrayable<string, string>
 */
final class ChatMessage implements Arrayable
{
    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    private function __construct(
        public readonly string $role,
        public readonly string $content,
    ) {}

    public static function user(string $content): self
    {
        return new self(self::ROLE_USER, $content);
    }

    public static function assistant(string $content): self
    {
        return new self(self::ROLE_ASSISTANT, $content);
    }

    /**
     * Build a message from a client payload, or null when the payload is not
     * one. Anything other than the two known roles, and anything with nothing
     * in it, is discarded rather than passed on to a provider.
     *
     * @param  array<array-key, mixed>  $payload
     */
    public static function fromArray(array $payload): ?self
    {
        $role = $payload['role'] ?? null;
        $content = $payload['content'] ?? null;

        if (! is_string($role) || ! is_string($content) || trim($content) === '') {
            return null;
        }

        return match ($role) {
            self::ROLE_USER => self::user($content),
            self::ROLE_ASSISTANT => self::assistant($content),
            default => null,
        };
    }

    public function isUser(): bool
    {
        return $this->role === self::ROLE_USER;
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function toArray(): array
    {
        return ['role' => $this->role, 'content' => $this->content];
    }
}

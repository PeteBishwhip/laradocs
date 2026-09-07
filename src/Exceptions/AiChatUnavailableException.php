<?php

declare(strict_types=1);

namespace Laradocs\Exceptions;

use Laradocs\Ai\ChatService;
use RuntimeException;

/**
 * Thrown when the AI chat is asked to answer while it is switched off, or
 * while the optional laravel/ai package it is built on is not installed.
 *
 * The HTTP endpoint never raises this: its middleware 404s first, so a
 * disabled assistant leaves no trace of itself. It exists for the PHP entry
 * point, {@see ChatService::ask()}, where silently answering
 * nothing would be the worse outcome.
 */
final class AiChatUnavailableException extends RuntimeException
{
    public static function disabled(): self
    {
        return new self('The Laradocs AI chat is disabled. Set laradocs.ai.enabled (LARADOCS_AI=true) to switch it on.');
    }

    public static function missingPackage(): self
    {
        return new self('The Laradocs AI chat needs the Laravel AI SDK. Run: composer require laravel/ai');
    }
}

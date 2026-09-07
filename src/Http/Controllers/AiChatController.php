<?php

declare(strict_types=1);

namespace Laradocs\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laradocs\Ai\ChatMessage;
use Laradocs\Ai\ChatRequest;
use Laradocs\Ai\ChatService;
use Laradocs\Support\Config;
use Laradocs\Support\Version;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The chat endpoint the widget talks to, and the documented integration point
 * for anything else that wants to ask the docs a question.
 *
 * The client owns the thread: it replays whichever prior turns it wants
 * remembered on every request, so the endpoint stays stateless and stores
 * nothing. Storing what a deployment does want kept is what
 * `Laradocs::onChat()` is for.
 *
 * Answers stream back as server-sent events by default. A client that cannot
 * read a stream, or a deployment behind a buffering proxy, gets one JSON
 * response instead by sending `stream: false` or setting `laradocs.ai.stream`
 * to false.
 */
final class AiChatController
{
    public function __construct(private readonly ChatService $chat) {}

    public function __invoke(Request $request): JsonResponse|Responsable
    {
        /** @var array{message: string, history?: array<int, array<string, mixed>>, version?: string|null, stream?: bool} $payload */
        $payload = $request->validate([
            'message' => ['required', 'string', 'max:' . max(1, Config::int('laradocs.ai.max_chars', 2000))],
            'history' => ['sometimes', 'array'],
            'history.*' => ['array'],
            'history.*.role' => ['required', 'string', 'in:user,assistant'],
            'history.*.content' => ['required', 'string'],
            'version' => ['sometimes', 'nullable', 'string'],
            'stream' => ['sometimes', 'boolean'],
        ]);

        $version = $this->activate($payload['version'] ?? null);

        $chatRequest = new ChatRequest(
            $payload['message'],
            $this->history($payload['history'] ?? []),
            app()->getLocale(),
            $version,
            $this->user(),
        );

        return $this->chat->streams() && ($payload['stream'] ?? true)
            ? $this->chat->stream($chatRequest)
            : $this->answer($chatRequest);
    }

    /**
     * Answer in one response, turning a provider failure into a 502 rather
     * than an unhandled exception: the assistant is a third party service and
     * its bad day is not the documentation's fault.
     */
    private function answer(ChatRequest $request): JsonResponse
    {
        try {
            return new JsonResponse($this->chat->ask($request)->toArray());
        } catch (Throwable $e) {
            report($e);

            return new JsonResponse([
                'error' => 'Assistant unavailable',
                'message' => 'The documentation assistant could not answer that. Please try again.',
            ], Response::HTTP_BAD_GATEWAY);
        }
    }

    /**
     * Turn the replayed thread into messages, keeping the most recent turns up
     * to the configured limit so a long conversation cannot grow the prompt
     * without bound.
     *
     * @param  array<int, array<string, mixed>>  $history
     * @return list<ChatMessage>
     */
    private function history(array $history): array
    {
        $messages = [];

        foreach ($history as $entry) {
            $message = ChatMessage::fromArray($entry);

            if ($message !== null) {
                $messages[] = $message;
            }
        }

        $limit = $this->chat->historyLimit();

        return $limit === 0 ? [] : array_slice($messages, -$limit);
    }

    /**
     * Make the version the reader is on the active one, so the assistant's
     * tools read that version's pages.
     *
     * The route drops SetDocsVersion and this takes its place, because a
     * streamed answer is generated while the response is being sent, by which
     * time that middleware would already have restored the previous handle.
     * The restore happens as the application terminates instead, which is what
     * keeps a long-lived worker (Laravel Octane) from carrying one reader's
     * version into the next request.
     *
     * A version this site does not recognise falls back to the default rather
     * than being refused: a stale bookmark in a widget should still get an
     * answer.
     */
    private function activate(?string $requested): ?string
    {
        $versions = Version::available();

        if ($versions === []) {
            return null;
        }

        $version = $requested !== null && Version::registry()->get($requested) !== null
            ? $requested
            : Version::default() ?? array_key_first($versions);

        $previous = Config::nullableString('laradocs._current_version');

        config(['laradocs._current_version' => $version]);

        app()->terminating(static function () use ($previous): void {
            config(['laradocs._current_version' => $previous]);
        });

        return $version;
    }

    /**
     * The reader, resolved through the configured guard when there is one so
     * the identity a callback sees is the identity the endpoint authorised.
     */
    private function user(): ?Authenticatable
    {
        $guard = Config::nullableString('laradocs.ai.auth.guard');

        return $guard === null || $guard === ''
            ? Auth::user()
            : Auth::guard($guard)->user();
    }
}

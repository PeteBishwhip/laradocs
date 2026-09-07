<?php

declare(strict_types=1);

namespace Laradocs\Ai;

use Closure;
use Illuminate\Http\Request;
use Throwable;

/**
 * Everything a deployment hangs off the assistant, in one place.
 *
 * Four kinds of callback, all registered from a service provider's `boot()`
 * through the facade, all optional:
 *
 *   - exchange handlers, for metering spend and storing transcripts;
 *   - context resolvers, for telling the assistant about the reader;
 *   - tool resolvers, for handing it tools the config cannot describe;
 *   - an authorizer, for the access rules a guard and a gate cannot express.
 *
 * Registrations mutate a singleton, so they belong in `boot()` and nowhere
 * else: a registration made mid-request survives into every later request on a
 * long-lived worker. The callbacks themselves are invoked per request and may
 * safely read per-request state.
 */
final class ChatRegistry
{
    /** @var list<Closure(ChatExchange): mixed> */
    private array $handlers = [];

    /** @var list<Closure(ChatRequest): mixed> */
    private array $context = [];

    /** @var list<Closure(ChatRequest): mixed> */
    private array $tools = [];

    /** @var (Closure(Request): mixed)|null */
    private ?Closure $authorizer = null;

    /**
     * @param  Closure(ChatExchange): mixed  $handler
     */
    public function onChat(Closure $handler): void
    {
        $this->handlers[] = $handler;
    }

    /**
     * @param  Closure(ChatRequest): mixed  $resolver
     */
    public function context(Closure $resolver): void
    {
        $this->context[] = $resolver;
    }

    /**
     * @param  Closure(ChatRequest): mixed  $resolver
     */
    public function tools(Closure $resolver): void
    {
        $this->tools[] = $resolver;
    }

    /**
     * @param  (Closure(Request): mixed)|null  $callback
     */
    public function authorize(?Closure $callback): void
    {
        $this->authorizer = $callback;
    }

    /**
     * Hand a finished exchange to every registered handler.
     *
     * A handler that throws is reported and the rest still run: the answer has
     * already been produced and paid for by this point, and for a streamed
     * answer the body has already reached the reader, so there is nothing left
     * to fail into.
     */
    public function dispatch(ChatExchange $exchange): void
    {
        foreach ($this->handlers as $handler) {
            try {
                $handler($exchange);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * The extra context to append to the assistant's instructions. Resolvers
     * may return a string, or anything iterable of strings; empty values are
     * dropped.
     *
     * @return list<string>
     */
    public function contextFor(ChatRequest $request): array
    {
        $lines = [];

        foreach ($this->context as $resolver) {
            foreach ($this->strings($resolver($request)) as $line) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * The extra tools to hand the assistant, flattened across every resolver.
     *
     * @return list<mixed>
     */
    public function toolsFor(ChatRequest $request): array
    {
        $tools = [];

        foreach ($this->tools as $resolver) {
            $resolved = $resolver($request);

            if (is_iterable($resolved)) {
                foreach ($resolved as $tool) {
                    $tools[] = $tool;
                }

                continue;
            }

            if ($resolved !== null) {
                $tools[] = $resolved;
            }
        }

        return $tools;
    }

    /**
     * Whether the registered authorizer allows this request. No authorizer
     * means no opinion, which is an allow: the guard and gate settings are the
     * ones that close the endpoint.
     */
    public function authorises(Request $request): bool
    {
        return $this->authorizer === null || (bool) ($this->authorizer)($request);
    }

    /**
     * @return list<string>
     */
    private function strings(mixed $value): array
    {
        if (is_string($value)) {
            return trim($value) === '' ? [] : [$value];
        }

        if (! is_iterable($value)) {
            return [];
        }

        $lines = [];

        foreach ($value as $item) {
            if (is_string($item) && trim($item) !== '') {
                $lines[] = $item;
            }
        }

        return $lines;
    }
}

<?php

declare(strict_types=1);

namespace Laradocs\Mcp;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Mcp\Server\Transport\HttpTransport;
use ReflectionClass;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bootstraps the laravel/mcp HTTP transport and hands the request to the
 * LaradocsMcpServer. Only loaded at runtime when laravel/mcp is installed.
 */
final class McpServerHandler
{
    /**
     * Whether the installed transport takes the session id as its second
     * constructor argument. Resolved once per process.
     */
    private static ?bool $transportTakesSessionId = null;

    public function handle(Request $request): Response|StreamedResponse
    {
        $transport = $this->makeTransport($request);

        $server = app(LaradocsMcpServer::class, ['transport' => $transport]);
        $server->start();

        return $transport->run();
    }

    /**
     * Build the transport for whichever laravel/mcp is installed.
     *
     * The constructor's second argument changed in laravel/mcp v1.0: it was a
     * required `string $sessionId`, and is now `?Closure $handler`, with the
     * session handled inside the transport. Passing the session id to v1
     * therefore fails with a TypeError, and omitting it on v0.9 fails with an
     * ArgumentCountError, so the shape is decided from the constructor itself
     * rather than from a version constraint.
     */
    private function makeTransport(Request $request): HttpTransport
    {
        // Instantiated reflectively because the constructor's shape is only
        // known at runtime: a literal `new` would be a static-analysis error
        // against whichever of the two signatures is not installed.
        return (new ReflectionClass(HttpTransport::class))
            ->newInstanceArgs($this->transportArguments($request));
    }

    /**
     * The constructor arguments for the installed transport.
     *
     * Separated from the instantiation so both shapes can be exercised on
     * whichever version happens to be installed: building the v0.9 argument
     * list under v1 is harmless, whereas constructing with it is a TypeError.
     *
     * @return array<int, mixed>
     */
    private function transportArguments(Request $request): array
    {
        $arguments = [$request];

        if ($this->transportTakesSessionId()) {
            $arguments[] = (string) $request->header('MCP-Session-Id');
        }

        return $arguments;
    }

    private function transportTakesSessionId(): bool
    {
        if (self::$transportTakesSessionId !== null) {
            return self::$transportTakesSessionId;
        }

        $parameters = (new ReflectionClass(HttpTransport::class))->getConstructor()?->getParameters() ?? [];

        return self::$transportTakesSessionId = ($parameters[1] ?? null)?->getName() === 'sessionId';
    }
}

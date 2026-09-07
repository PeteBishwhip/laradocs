<?php

declare(strict_types=1);

namespace Laradocs\Ai;

use Laradocs\Support\Config;
use Laravel\Mcp\Client;
use Laravel\Mcp\Client\Primitives\Tool;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Turns the `laradocs.ai.mcp.servers` config into tools the assistant can
 * call.
 *
 * Each entry is either an HTTP server (`url`, with an optional bearer `token`
 * and extra `headers`) or a local one spoken to over stdio (`command` and
 * `args`). Whichever it is, the server is asked what it advertises and each
 * advertised tool is handed straight to the SDK, which wraps a client tool
 * primitive on sight.
 *
 * A third party server is a third party outage waiting to happen, so one that
 * cannot be reached or that answers with nonsense is logged and skipped. The
 * reader still gets an answer from the tools that did come back, which is a
 * better failure than a 500 on every question because someone else's server
 * is down. The logger is injected rather than reached for through the facade
 * so a test can read back what was written without standing a mock in front
 * of everything else that logs.
 */
final class McpServers
{
    public function __construct(private readonly LoggerInterface $logger) {}

    /**
     * The tools advertised by every configured server, in config order.
     *
     * @return list<Tool>
     */
    public function tools(): array
    {
        // @codeCoverageIgnoreStart
        // Configuring a server without laravel/mcp installed is a
        // misconfiguration rather than a client error, and unreachable
        // wherever the package is present, which is everywhere the coverage
        // suite runs. AiChatMcpServersTest skips itself without it.
        if (! class_exists(Client::class)) {
            return [];
        }
        // @codeCoverageIgnoreEnd

        $tools = [];

        foreach (Config::array('laradocs.ai.mcp.servers') as $name => $definition) {
            if (! is_array($definition)) {
                continue;
            }

            foreach ($this->toolsFor((string) $name, $definition) as $tool) {
                $tools[] = $tool;
            }
        }

        return $tools;
    }

    /**
     * @param  array<array-key, mixed>  $definition
     * @return list<Tool>
     */
    private function toolsFor(string $name, array $definition): array
    {
        try {
            $client = $this->client($definition);

            if ($client === null) {
                $this->logger->warning('Laradocs skipped the AI chat MCP server [' . $name . ']: it declares neither a "url" nor a "command".');

                return [];
            }

            return $this->filter(array_values($client->connect()->tools()->all()), $definition);
        } catch (Throwable $e) {
            $this->logger->warning('Laradocs could not read tools from the AI chat MCP server [' . $name . ']: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * @param  array<array-key, mixed>  $definition
     */
    private function client(array $definition): ?Client
    {
        $url = $definition['url'] ?? null;
        $command = $definition['command'] ?? null;

        $client = match (true) {
            is_string($url) && $url !== '' => $this->web($url, $definition),
            is_string($command) && $command !== '' => Client::local($command, $this->strings($definition['args'] ?? [])),
            default => null,
        };

        $timeout = $definition['timeout'] ?? null;

        return $client !== null && is_numeric($timeout)
            ? $client->withTimeout((float) $timeout)
            : $client;
    }

    /**
     * @param  array<array-key, mixed>  $definition
     */
    private function web(string $url, array $definition): Client
    {
        $client = Client::web($url);

        $token = $definition['token'] ?? null;

        if (is_string($token) && $token !== '') {
            $client = $client->withToken($token);
        }

        $headers = $this->headers($definition['headers'] ?? null);

        return $headers === [] ? $client : $client->withHeaders($headers);
    }

    /**
     * The headers to send alongside every call to a server. Anything that is
     * not a name mapped to a scalar is dropped rather than coerced, since a
     * header built out of an array is not a header anyone meant to send.
     *
     * @return array<string, string>
     */
    private function headers(mixed $headers): array
    {
        if (! is_array($headers)) {
            return [];
        }

        $mapped = [];

        foreach ($headers as $name => $value) {
            if (is_string($name) && is_scalar($value)) {
                $mapped[$name] = (string) $value;
            }
        }

        return $mapped;
    }

    /**
     * Narrow a server's advertised tools to the ones the config allows. An
     * "only" list wins over an "except" list, since naming what you want is
     * the more explicit of the two.
     *
     * @param  list<Tool>  $tools
     * @param  array<array-key, mixed>  $definition
     * @return list<Tool>
     */
    private function filter(array $tools, array $definition): array
    {
        $only = $this->strings($definition['only'] ?? []);

        if ($only !== []) {
            return array_values(array_filter(
                $tools,
                static fn (Tool $tool): bool => in_array($tool->name, $only, true),
            ));
        }

        $except = $this->strings($definition['except'] ?? []);

        return array_values(array_filter(
            $tools,
            static fn (Tool $tool): bool => ! in_array($tool->name, $except, true),
        ));
    }

    /**
     * @return list<string>
     */
    private function strings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $strings = [];

        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $strings[] = $item;
            }
        }

        return $strings;
    }
}

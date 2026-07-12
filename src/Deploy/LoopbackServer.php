<?php

declare(strict_types=1);

namespace Laradocs\Deploy;

/**
 * A single-shot loopback HTTP listener used to capture the OAuth redirect.
 * Binds 127.0.0.1, accepts one connection, replies with a "you can close this
 * tab" page, and hands back the parsed query string.
 */
final class LoopbackServer
{
    /**
     * @var resource
     */
    private $socket;
    /**
     * @var int
     */
    private $port;
    /**
     * @param  resource  $socket
     */
    private function __construct($socket, int $port)
    {
        $this->socket = $socket;
        $this->port = $port;
    }

    public static function start(int $port): self
    {
        $socket = @stream_socket_server("tcp://127.0.0.1:{$port}", $errno, $errstr);

        if ($socket === false) {
            throw new DeployException("Could not start the local OAuth listener on 127.0.0.1:{$port}: {$errstr}");
        }

        $name = (string) stream_socket_get_name($socket, false);
        $boundPort = (int) substr($name, (int) strrpos($name, ':') + 1);

        return new self($socket, $boundPort);
    }

    public function port(): int
    {
        return $this->port;
    }

    /**
     * Accept the browser redirect, reply, and return its query parameters.
     *
     * @return array<string, string>
     */
    public function awaitCallback(int $timeout = 300): array
    {
        $connection = @stream_socket_accept($this->socket, $timeout);

        if ($connection === false) {
            throw new DeployException('Timed out waiting for the browser to complete authorization.');
        }

        try {
            $request = (string) fread($connection, 16384);

            $body = '<!doctype html><html><body style="font-family:system-ui;text-align:center;padding:3rem">'
                . '<h2>Laradocs CLI connected</h2><p>You can close this tab and return to your terminal.</p></body></html>';

            fwrite($connection, "HTTP/1.1 200 OK\r\nContent-Type: text/html\r\nContent-Length: " . strlen($body) . "\r\nConnection: close\r\n\r\n" . $body);

            return $this->parse($request);
        } finally {
            fclose($connection);
        }
    }

    public function close(): void
    {
        fclose($this->socket);
    }

    /**
     * Pull the query parameters out of a raw HTTP request.
     *
     * @return array<string, string>
     */
    public function parse(string $request): array
    {
        $line = strtok($request, "\r\n") ?: '';
        $target = explode(' ', $line)[1] ?? '';

        parse_str((string) parse_url($target, PHP_URL_QUERY), $parsed);

        $query = [];
        foreach ($parsed as $key => $value) {
            $query[(string) $key] = Json::string($value);
        }

        return $query;
    }
}

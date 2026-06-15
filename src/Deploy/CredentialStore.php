<?php

declare(strict_types=1);

namespace Laradocs\Deploy;

use Illuminate\Filesystem\Filesystem;
use Laradocs\Support\Config;

/**
 * Persists OAuth tokens for the deploy CLI on disk, keyed by API url so a
 * machine can hold credentials for more than one Laradocs platform.
 */
final class CredentialStore
{
    public function __construct(private Filesystem $files) {}

    /**
     * @return array{access_token:string,refresh_token:?string,token_type:string,expires_at:?int}|null
     */
    public function forUrl(string $url): ?array
    {
        $all = $this->all();
        $entry = $all[$this->key($url)] ?? null;

        if (! is_array($entry) || ! isset($entry['access_token'])) {
            return null;
        }

        return [
            'access_token' => Json::string($entry['access_token']),
            'refresh_token' => Json::nullableString($entry['refresh_token'] ?? null),
            'token_type' => Json::string($entry['token_type'] ?? null, 'Bearer'),
            'expires_at' => isset($entry['expires_at']) ? Json::int($entry['expires_at']) : null,
        ];
    }

    /**
     * Store a token-endpoint response, translating expires_in into an absolute
     * expiry (with a little clock-skew headroom).
     *
     * @param  array<string, mixed>  $token
     */
    public function store(string $url, array $token): void
    {
        $all = $this->all();

        $expiresIn = isset($token['expires_in']) ? Json::int($token['expires_in']) : null;

        $all[$this->key($url)] = [
            'access_token' => Json::string($token['access_token'] ?? null),
            'refresh_token' => Json::nullableString($token['refresh_token'] ?? null),
            'token_type' => Json::string($token['token_type'] ?? null, 'Bearer'),
            'expires_at' => $expiresIn !== null ? time() + $expiresIn - 30 : null,
        ];

        $this->write($all);
    }

    public function forget(string $url): void
    {
        $all = $this->all();
        unset($all[$this->key($url)]);

        $this->write($all);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $path = $this->path();

        if (! $this->files->exists($path)) {
            return [];
        }

        return Json::object(json_decode((string) $this->files->get($path), true));
    }

    public function path(): string
    {
        $configured = Config::nullableString('laradocs.deploy.credentials');

        return $configured ?: storage_path('laradocs/credentials.json');
    }

    private function key(string $url): string
    {
        return rtrim($url, '/');
    }

    /**
     * @param  array<string, mixed>  $all
     */
    private function write(array $all): void
    {
        $path = $this->path();

        $this->files->ensureDirectoryExists($this->files->dirname($path));
        $this->files->put($path, (string) json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Tokens are sensitive; keep them owner-only where the OS supports it.
        @chmod($path, 0600);
    }
}

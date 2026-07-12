<?php

declare(strict_types=1);

namespace Laradocs\Deploy;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Laradocs\Support\Config;

/**
 * Thin client over the hosted Laradocs deploy API. Handles bearer auth and
 * transparently refreshes an expired access token using the stored refresh
 * token before falling back to a hard "please log in" error.
 */
final class ApiClient
{
    /**
     * @var \Laradocs\Deploy\CredentialStore
     */
    private $credentials;
    /**
     * @var \Laradocs\Deploy\OAuthFlow
     */
    private $oauth;
    public function __construct(CredentialStore $credentials, OAuthFlow $oauth)
    {
        $this->credentials = $credentials;
        $this->oauth = $oauth;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listSites(): array
    {
        $data = $this->get('/api/v1/sites')['data'] ?? [];

        if (! is_array($data)) {
            return [];
        }

        return array_values(array_map(
            static function ($site): array {
                return Json::object($site);
            },
            $data,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function getSite(string $slug): array
    {
        return Json::object($this->get("/api/v1/sites/{$slug}")['data'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function deploy(string $slug, array $payload): array
    {
        return $this->post("/api/v1/sites/{$slug}/deploy", $payload);
    }

    /**
     * @return array<string, string>
     */
    public function files(string $slug): array
    {
        $data = Json::object($this->get("/api/v1/sites/{$slug}/files")['data'] ?? []);

        return array_map(
            static function ($contents): string {
                return Json::string($contents);
            },
            Json::object($data['files'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(string $slug): array
    {
        return Json::object($this->get("/api/v1/sites/{$slug}/config")['data'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function updateConfig(string $slug, array $config): array
    {
        return Json::object($this->patch("/api/v1/sites/{$slug}/config", ['config' => $config])['data'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $path): array
    {
        return $this->send($path, function (PendingRequest $request) use ($path): Response {
            return $request->get($this->url($path));
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        return $this->send($path, function (PendingRequest $request) use ($path, $payload): Response {
            return $request->post($this->url($path), $payload);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function patch(string $path, array $payload): array
    {
        return $this->send($path, function (PendingRequest $request) use ($path, $payload): Response {
            return $request->patch($this->url($path), $payload);
        });
    }

    /**
     * Run a request, retrying once with a freshly refreshed token on a 401.
     *
     * @param  callable(PendingRequest): Response  $perform
     * @return array<string, mixed>
     */
    private function send(string $path, callable $perform): array
    {
        $response = $perform($this->authedRequest());

        if ($response->status() === 401) {
            $response = $perform($this->authedRequest(true));
        }

        if ($response->failed()) {
            throw new ApiException(
                "Request to {$path} failed (HTTP {$response->status()}).",
                $response->status(),
                Json::object($response->json()),
            );
        }

        return Json::object($response->json());
    }

    private function authedRequest(bool $forceRefresh = false): PendingRequest
    {
        return Http::acceptJson()->asJson()->withToken($this->accessToken($forceRefresh));
    }

    private function accessToken(bool $forceRefresh = false): string
    {
        $token = $this->credentials->forUrl($this->baseUrl());

        if ($token === null) {
            throw NotAuthenticatedException::make();
        }

        $expired = $token['expires_at'] !== null && $token['expires_at'] <= time();

        if (($forceRefresh || $expired) && $token['refresh_token'] !== null) {
            $refreshed = $this->oauth->refresh($token['refresh_token']);
            $this->credentials->store($this->baseUrl(), $refreshed);

            return Json::string($refreshed['access_token'] ?? null);
        }

        return $token['access_token'];
    }

    private function url(string $path): string
    {
        return $this->baseUrl() . $path;
    }

    private function baseUrl(): string
    {
        return rtrim(Config::string('laradocs.deploy.url', 'https://laradocs.dev'), '/');
    }
}

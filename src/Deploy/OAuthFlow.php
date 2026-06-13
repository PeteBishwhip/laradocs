<?php

declare(strict_types=1);

namespace Laradocs\Deploy;

use Illuminate\Support\Facades\Http;
use Laradocs\Support\Config;

/**
 * Drives the OAuth2 authorization-code + PKCE flow for the CLI: spins up a
 * loopback listener, opens the browser to the platform's consent screen, and
 * exchanges the returned code for tokens. Also refreshes expired tokens.
 */
class OAuthFlow
{
    private const SCOPES = 'deploy docs:read config:read config:write';

    /**
     * Run the interactive login. $onPrompt receives the authorize URL so the
     * caller can display it in case the browser does not open on its own.
     *
     * @param  callable(string):void|null  $onPrompt
     * @return array<string, mixed> the token-endpoint response
     */
    public function login(?callable $onPrompt = null): array
    {
        $verifier = $this->codeVerifier();
        $challenge = $this->codeChallenge($verifier);
        $state = bin2hex(random_bytes(16));

        $url = $this->authorizeUrl($challenge, $state);

        if ($onPrompt !== null) {
            $onPrompt($url);
        }

        $query = $this->awaitAuthorization($url);

        $this->guardCallback($state, $query);

        return $this->exchangeCode($this->codeFrom($query), $verifier);
    }

    /**
     * Exchange a refresh token for a fresh access token.
     *
     * @return array<string, mixed>
     */
    public function refresh(string $refreshToken): array
    {
        $response = Http::asForm()->acceptJson()->post($this->tokenEndpoint(), [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->clientId(),
            'scope' => self::SCOPES,
        ]);

        if ($response->failed()) {
            throw new DeployException('Could not refresh the access token. Run `php artisan laradocs:login` again.');
        }

        return Json::object($response->json());
    }

    /**
     * Open the browser and block until the loopback listener captures the
     * redirect. Isolated so it can be exercised with a real socket in tests.
     *
     * @return array<string, string>
     */
    protected function awaitAuthorization(string $authorizeUrl): array
    {
        $server = LoopbackServer::start($this->port());

        try {
            $this->openBrowser($authorizeUrl);

            return $server->awaitCallback();
        } finally {
            $server->close();
        }
    }

    protected function openBrowser(string $url): void
    {
        $this->exec($this->browserCommand(PHP_OS_FAMILY) . ' ' . escapeshellarg($url) . ' > /dev/null 2>&1 &');
    }

    /**
     * The shell command used to open a URL on the given OS family.
     */
    public function browserCommand(string $osFamily): string
    {
        return match ($osFamily) {
            'Darwin' => 'open',
            'Windows' => 'start ""',
            default => 'xdg-open',
        };
    }

    protected function exec(string $command): void
    {
        exec($command);
    }

    /**
     * @param  array<string, string>  $query
     */
    protected function guardCallback(string $state, array $query): void
    {
        if (isset($query['error'])) {
            throw new DeployException('Authorization was denied: ' . $query['error']);
        }

        if (! isset($query['state']) || ! hash_equals($state, $query['state'])) {
            throw new DeployException('OAuth state mismatch; aborting for safety.');
        }
    }

    /**
     * @param  array<string, string>  $query
     */
    protected function codeFrom(array $query): string
    {
        if (! isset($query['code']) || $query['code'] === '') {
            throw new DeployException('The authorization callback did not include a code.');
        }

        return $query['code'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function exchangeCode(string $code, string $verifier): array
    {
        $response = Http::asForm()->acceptJson()->post($this->tokenEndpoint(), [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'code_verifier' => $verifier,
            'code' => $code,
        ]);

        if ($response->failed()) {
            throw new DeployException('Token exchange failed (HTTP ' . $response->status() . '). ' . $response->body());
        }

        return Json::object($response->json());
    }

    private function codeVerifier(): string
    {
        return $this->base64Url(random_bytes(64));
    }

    private function codeChallenge(string $verifier): string
    {
        return $this->base64Url(hash('sha256', $verifier, true));
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function authorizeUrl(string $challenge, string $state): string
    {
        $query = http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);

        return $this->baseUrl() . '/oauth/authorize?' . $query;
    }

    private function baseUrl(): string
    {
        return rtrim(Config::string('laradocs.deploy.url', 'https://laradocs.dev'), '/');
    }

    private function tokenEndpoint(): string
    {
        return $this->baseUrl() . '/oauth/token';
    }

    private function port(): int
    {
        return Config::int('laradocs.deploy.redirect_port', 8788);
    }

    private function redirectUri(): string
    {
        return "http://127.0.0.1:{$this->port()}/callback";
    }

    private function clientId(): string
    {
        return Config::string('laradocs.deploy.client_id');
    }
}

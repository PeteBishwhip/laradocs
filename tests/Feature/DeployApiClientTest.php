<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Laradocs\Deploy\ApiClient;
use Laradocs\Deploy\ApiException;
use Laradocs\Deploy\CredentialStore;
use Laradocs\Deploy\NotAuthenticatedException;

const API_URL = 'https://api.test';

beforeEach(function (): void {
    $dir = sys_get_temp_dir() . '/laradocs-apiclient-' . bin2hex(random_bytes(6));
    (new Filesystem)->ensureDirectoryExists($dir);
    $this->tempDocs[] = $dir;

    config()->set('laradocs.deploy.url', API_URL);
    config()->set('laradocs.deploy.credentials', $dir . '/credentials.json');
    config()->set('laradocs.deploy.client_id', 'test-client');
});

function seedApiToken(int $expiresIn = 3600): void
{
    app(CredentialStore::class)->store(API_URL, [
        'access_token' => 'access-token',
        'refresh_token' => 'refresh-token',
        'expires_in' => $expiresIn,
    ]);
}

it('lists sites and normalises each entry', function () {
    seedApiToken();

    Http::fake([API_URL . '/api/v1/sites' => Http::response(['data' => [['slug' => 'a'], ['slug' => 'b']]])]);

    $sites = app(ApiClient::class)->listSites();

    expect($sites)->toHaveCount(2)
        ->and($sites[0]['slug'])->toBe('a');
});

it('returns an empty list when sites data is not an array', function () {
    seedApiToken();

    Http::fake([API_URL . '/api/v1/sites' => Http::response(['data' => 'unexpected'])]);

    expect(app(ApiClient::class)->listSites())->toBe([]);
});

it('maps a site\'s files to a path => contents array', function () {
    seedApiToken();

    Http::fake([API_URL . '/api/v1/sites/acme/files' => Http::response([
        'data' => ['files' => ['index.md' => '# Index']],
    ])]);

    expect(app(ApiClient::class)->files('acme'))->toBe(['index.md' => '# Index']);
});

it('throws when there is no stored credential', function () {
    expect(function () {
        return app(ApiClient::class)->getSite('acme');
    })->toThrow(NotAuthenticatedException::class);
});

it('refreshes an expired access token before the request', function () {
    seedApiToken(-100); // already expired

    Http::fake([
        API_URL . '/oauth/token' => Http::response(['access_token' => 'refreshed', 'refresh_token' => 'r2', 'expires_in' => 3600]),
        API_URL . '/api/v1/sites/acme' => Http::response(['data' => ['slug' => 'acme']]),
    ]);

    $site = app(ApiClient::class)->getSite('acme');

    expect($site['slug'])->toBe('acme')
        ->and(app(CredentialStore::class)->forUrl(API_URL)['access_token'])->toBe('refreshed');

    Http::assertSent(function ($request) {
        return strpos($request->url(), '/oauth/token') !== false
            && $request['grant_type'] === 'refresh_token';
    });
});

it('refreshes and retries once on a 401', function () {
    seedApiToken();

    Http::fake([
        API_URL . '/oauth/token' => Http::response(['access_token' => 'refreshed', 'expires_in' => 3600]),
        API_URL . '/api/v1/sites/acme' => Http::sequence()
            ->push([], 401)
            ->push(['data' => ['slug' => 'acme']], 200),
    ]);

    expect(app(ApiClient::class)->getSite('acme')['slug'])->toBe('acme');

    Http::assertSent(function ($request) {
        return strpos($request->url(), '/oauth/token') !== false;
    });
});

it('throws an ApiException on a non-401 failure', function () {
    seedApiToken();

    Http::fake([API_URL . '/api/v1/sites/acme' => Http::response(['message' => 'Server error'], 500)]);

    expect(function () {
        return app(ApiClient::class)->getSite('acme');
    })
        ->toThrow(ApiException::class);
});

<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Laradocs\Deploy\CredentialStore;
use Laradocs\Deploy\OAuthFlow;

const DEPLOY_URL = 'https://deploy.test';

beforeEach(function (): void {
    $dir = sys_get_temp_dir() . '/laradocs-cli-' . bin2hex(random_bytes(6));
    (new Filesystem)->ensureDirectoryExists($dir);
    $this->tempDocs[] = $dir;

    config()->set('laradocs.deploy.url', DEPLOY_URL);
    config()->set('laradocs.deploy.credentials', $dir . '/credentials.json');
    config()->set('laradocs.deploy.client_id', 'test-client');
});

function seedToken(): void
{
    app(CredentialStore::class)->store(DEPLOY_URL, [
        'access_token' => 'access-token',
        'refresh_token' => 'refresh-token',
        'token_type' => 'Bearer',
        'expires_in' => 3600,
    ]);
}

/*
|--------------------------------------------------------------------------
| laradocs:login
|--------------------------------------------------------------------------
*/

function fakeOAuth(callable $login): void
{
    app()->instance(OAuthFlow::class, new class($login) extends OAuthFlow
    {
        /** @var callable */
        private $login;

        public function __construct(callable $login)
        {
            $this->login = $login;
        }

        public function login(?callable $onPrompt = null): array
        {
            return ($this->login)($onPrompt);
        }
    });
}

it('logs in and stores the issued token', function () {
    fakeOAuth(function (?callable $onPrompt) {
        $onPrompt('https://deploy.test/oauth/authorize?x=1');

        return ['access_token' => 'fresh', 'refresh_token' => 'r', 'expires_in' => 3600];
    });

    $this->artisan('laradocs:login')
        ->expectsOutputToContain('oauth/authorize')
        ->assertSuccessful();

    expect(app(CredentialStore::class)->forUrl(DEPLOY_URL)['access_token'])->toBe('fresh');
});

it('honours the --url option at login', function () {
    fakeOAuth(fn (?callable $onPrompt) => ['access_token' => 'fresh', 'expires_in' => 3600]);

    $this->artisan('laradocs:login', ['--url' => 'https://other.test'])->assertSuccessful();

    expect(app(CredentialStore::class)->forUrl('https://other.test'))->not->toBeNull();
});

it('fails login when authorization errors', function () {
    fakeOAuth(function (?callable $onPrompt) {
        throw new RuntimeException('user closed the browser');
    });

    $this->artisan('laradocs:login')
        ->expectsOutputToContain('user closed the browser')
        ->assertFailed();
});

/*
|--------------------------------------------------------------------------
| laradocs:deploy
|--------------------------------------------------------------------------
*/

it('uploads local markdown for a flat-site deploy', function () {
    seedToken();
    $this->makeDocs(['index.md' => '# Index', 'guide/intro.md' => '# Intro']);

    Http::fake([
        DEPLOY_URL . '/api/v1/sites/acme' => Http::response(['data' => ['slug' => 'acme', 'source' => 'flat']]),
        DEPLOY_URL . '/api/v1/sites/acme/deploy' => Http::response([
            'data' => ['slug' => 'acme'],
            'deployment' => ['status' => 'success', 'files_written' => 2, 'files_pruned' => 0],
        ]),
    ]);

    $this->artisan('laradocs:deploy', ['--site' => 'acme'])->assertSuccessful();

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/deploy')
        && ($request->data()['files']['index.md'] ?? null) === '# Index'
        && ($request->data()['files']['guide/intro.md'] ?? null) === '# Intro');
});

it('deploys a git site from a tag ref', function () {
    seedToken();

    Http::fake([
        DEPLOY_URL . '/api/v1/sites/acme' => Http::response(['data' => ['slug' => 'acme', 'source' => 'github']]),
        DEPLOY_URL . '/api/v1/sites/acme/deploy' => Http::response([
            'data' => ['slug' => 'acme'],
            'deployment' => ['status' => 'success', 'files_written' => 3, 'files_pruned' => 1],
        ]),
    ]);

    $this->artisan('laradocs:deploy', ['--site' => 'acme', '--tag' => 'v1.2.0'])->assertSuccessful();

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/deploy')
        && ($request->data()['ref'] ?? null) === 'v1.2.0');
});

it('deploys a git site from the connected branch and reports a queued deploy', function () {
    seedToken();

    Http::fake([
        DEPLOY_URL . '/api/v1/sites/acme' => Http::response(['data' => ['slug' => 'acme', 'source' => 'github']]),
        DEPLOY_URL . '/api/v1/sites/acme/deploy' => Http::response(['data' => ['slug' => 'acme']]),
    ]);

    $this->artisan('laradocs:deploy', ['--site' => 'acme'])
        ->expectsOutputToContain('queued')
        ->assertSuccessful();

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/deploy')
        && ! array_key_exists('ref', $request->data()));
});

it('fails the deploy when not authenticated', function () {
    $this->makeDocs(['index.md' => '# Index']);

    $this->artisan('laradocs:deploy', ['--site' => 'acme'])
        ->assertFailed()
        ->expectsOutputToContain('laradocs:login');
});

it('fails the deploy when no site is specified', function () {
    seedToken();
    config()->set('laradocs.deploy.site', null);

    $this->artisan('laradocs:deploy')->assertFailed();
});

it('refuses a flat deploy with no local files', function () {
    seedToken();
    $this->makeDocs(['placeholder.txt' => 'not markdown']);

    Http::fake([
        DEPLOY_URL . '/api/v1/sites/acme' => Http::response(['data' => ['slug' => 'acme', 'source' => 'flat']]),
    ]);

    $this->artisan('laradocs:deploy', ['--site' => 'acme'])->assertFailed();
});

it('surfaces a recorded deploy failure message', function () {
    seedToken();

    Http::fake([
        DEPLOY_URL . '/api/v1/sites/acme' => Http::response(['data' => ['slug' => 'acme', 'source' => 'github']]),
        DEPLOY_URL . '/api/v1/sites/acme/deploy' => Http::response([
            'deployment' => ['status' => 'failed', 'message' => "GitHub ref 'nope' was not found in acme/docs."],
        ], 422),
    ]);

    $this->artisan('laradocs:deploy', ['--site' => 'acme', '--ref' => 'nope'])
        ->assertFailed()
        ->expectsOutputToContain('was not found');
});

/*
|--------------------------------------------------------------------------
| laradocs:clone-project
|--------------------------------------------------------------------------
*/

it('clones a remote site into an empty docs directory', function () {
    seedToken();
    $root = $this->makeDocs([]);

    Http::fake([
        DEPLOY_URL . '/api/v1/sites/acme/files' => Http::response([
            'data' => ['files' => ['index.md' => '# Remote', 'guide/intro.md' => '# Intro']],
        ]),
    ]);

    $this->artisan('laradocs:clone-project', ['--site' => 'acme'])->assertSuccessful();

    expect(file_get_contents($root . '/index.md'))->toBe('# Remote')
        ->and(file_get_contents($root . '/guide/intro.md'))->toBe('# Intro');
});

it('refuses to clone over a non-empty docs directory without --force', function () {
    seedToken();
    $this->makeDocs(['existing.md' => '# Keep me']);

    $this->artisan('laradocs:clone-project', ['--site' => 'acme'])->assertFailed();
});

it('fails clone-project when no site is specified', function () {
    seedToken();
    config()->set('laradocs.deploy.site', null);

    $this->artisan('laradocs:clone-project')->assertFailed();
});

it('warns when the remote site has no files to clone', function () {
    seedToken();
    $this->makeDocs([]);

    Http::fake([DEPLOY_URL . '/api/v1/sites/acme/files' => Http::response(['data' => ['files' => []]])]);

    $this->artisan('laradocs:clone-project', ['--site' => 'acme'])
        ->expectsOutputToContain('no files')
        ->assertSuccessful();
});

it('fails clone-project when not authenticated', function () {
    $this->makeDocs([]);

    $this->artisan('laradocs:clone-project', ['--site' => 'acme'])
        ->assertFailed()
        ->expectsOutputToContain('laradocs:login');
});

it('reports an API error during clone-project', function () {
    seedToken();
    $this->makeDocs([]);

    Http::fake([DEPLOY_URL . '/api/v1/sites/acme/files' => Http::response(['message' => 'Server boom'], 500)]);

    $this->artisan('laradocs:clone-project', ['--site' => 'acme'])->assertFailed();
});

/*
|--------------------------------------------------------------------------
| laradocs:config
|--------------------------------------------------------------------------
*/

it('lists all remote config as a table', function () {
    seedToken();

    Http::fake([DEPLOY_URL . '/api/v1/sites/acme/config' => Http::response([
        'data' => ['config' => ['accent' => '#ff0000', 'preset' => 'wide']],
    ])]);

    $this->artisan('laradocs:config', ['--site' => 'acme'])
        ->expectsOutputToContain('#ff0000')
        ->assertSuccessful();
});

it('reads a single remote config value', function () {
    seedToken();

    Http::fake([DEPLOY_URL . '/api/v1/sites/acme/config' => Http::response([
        'data' => ['config' => ['accent' => '#ff0000', 'preset' => 'wide']],
    ])]);

    $this->artisan('laradocs:config', ['key' => 'accent', '--site' => 'acme'])
        ->expectsOutputToContain('#ff0000')
        ->assertSuccessful();
});

it('updates a remote config value', function () {
    seedToken();

    Http::fake([DEPLOY_URL . '/api/v1/sites/acme/config' => Http::response(['data' => ['config' => ['accent' => '#000000']]])]);

    $this->artisan('laradocs:config', ['key' => 'accent', 'value' => '#000000', '--site' => 'acme'])->assertSuccessful();

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && ($request->data()['config']['accent'] ?? null) === '#000000');
});

it('fails config when no site is specified', function () {
    seedToken();
    config()->set('laradocs.deploy.site', null);

    $this->artisan('laradocs:config')->assertFailed();
});

it('fails config when not authenticated', function () {
    $this->artisan('laradocs:config', ['--site' => 'acme'])
        ->assertFailed()
        ->expectsOutputToContain('laradocs:login');
});

it('reports an API error during config', function () {
    seedToken();

    Http::fake([DEPLOY_URL . '/api/v1/sites/acme/config' => Http::response(['message' => 'nope'], 500)]);

    $this->artisan('laradocs:config', ['--site' => 'acme'])->assertFailed();
});

it('reports when local config already matches the remote on sync', function () {
    seedToken();
    config(['laradocs.ui.accent' => '#ff0000', 'laradocs.ui.brand.title' => null, 'laradocs.ui.brand.logo' => null, 'laradocs.ui.preset' => null]);

    Http::fake([DEPLOY_URL . '/api/v1/sites/acme/config' => Http::response(['data' => ['config' => ['accent' => '#ff0000']]])]);

    $this->artisan('laradocs:config', ['--site' => 'acme', '--sync' => true])
        ->expectsOutputToContain('already matches')
        ->assertSuccessful();
});

it('pushes local config to the remote on sync when confirmed', function () {
    seedToken();
    config(['laradocs.ui.accent' => '#123456', 'laradocs.ui.brand.title' => null, 'laradocs.ui.brand.logo' => null, 'laradocs.ui.preset' => null]);

    Http::fake([DEPLOY_URL . '/api/v1/sites/acme/config' => Http::response(['data' => ['config' => ['accent' => '#000000']]])]);

    $this->artisan('laradocs:config', ['--site' => 'acme', '--sync' => true])
        ->expectsConfirmation('Push these local values to the remote site?', 'yes')
        ->assertSuccessful();

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && ($request->data()['config']['accent'] ?? null) === '#123456');
});

it('does not push on sync when declined', function () {
    seedToken();
    config(['laradocs.ui.accent' => '#123456', 'laradocs.ui.brand.title' => null, 'laradocs.ui.brand.logo' => null, 'laradocs.ui.preset' => null]);

    Http::fake([DEPLOY_URL . '/api/v1/sites/acme/config' => Http::response(['data' => ['config' => ['accent' => '#000000']]])]);

    $this->artisan('laradocs:config', ['--site' => 'acme', '--sync' => true])
        ->expectsConfirmation('Push these local values to the remote site?', 'no')
        ->assertSuccessful();

    Http::assertNotSent(fn ($request) => $request->method() === 'PATCH');
});

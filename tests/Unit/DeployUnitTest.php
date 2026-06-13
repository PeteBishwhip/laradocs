<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Laradocs\Deploy\ApiException;
use Laradocs\Deploy\CredentialStore;
use Laradocs\Deploy\Json;
use Laradocs\Deploy\LocalDocs;
use Laradocs\Deploy\LoopbackServer;
use Laradocs\Deploy\OAuthFlow;

/*
|--------------------------------------------------------------------------
| Json
|--------------------------------------------------------------------------
*/

it('coerces values through Json', function () {
    expect(Json::object(['a' => 1]))->toBe(['a' => 1])
        ->and(Json::object('nope'))->toBe([])
        ->and(Json::object([0 => 'x']))->toBe(['0' => 'x'])
        ->and(Json::string(5))->toBe('5')
        ->and(Json::string(['x'], 'd'))->toBe('d')
        ->and(Json::nullableString('x'))->toBe('x')
        ->and(Json::nullableString(['x']))->toBeNull()
        ->and(Json::int('7'))->toBe(7)
        ->and(Json::int('x', 9))->toBe(9);
});

/*
|--------------------------------------------------------------------------
| ApiException
|--------------------------------------------------------------------------
*/

it('extracts the best user message from an ApiException', function () {
    expect((new ApiException('x', 422, ['deployment' => ['message' => 'sync failed']]))->userMessage())
        ->toBe('sync failed');

    expect((new ApiException('x', 422, ['message' => 'Invalid.', 'errors' => ['files' => ['bad path']]]))->userMessage())
        ->toBe('bad path');

    expect((new ApiException('x', 422, ['message' => 'Just a message']))->userMessage())
        ->toBe('Just a message');

    expect((new ApiException('x', 422, ['message' => 'Empty errors', 'errors' => []]))->userMessage())
        ->toBe('Empty errors');

    expect((new ApiException('fallback', 500, ['deployment' => 'not-an-array']))->userMessage())
        ->toBe('fallback');
});

/*
|--------------------------------------------------------------------------
| CredentialStore
|--------------------------------------------------------------------------
*/

function credentialStore(): CredentialStore
{
    return new CredentialStore(new Filesystem);
}

beforeEach(function () {
    $this->credPath = sys_get_temp_dir() . '/laradocs-cred-' . bin2hex(random_bytes(5)) . '.json';
    config(['laradocs.deploy.credentials' => $this->credPath]);
});

afterEach(function () {
    if (isset($this->credPath) && is_string($this->credPath)) {
        @unlink($this->credPath);
    }
});

it('stores and reads credentials with a computed expiry', function () {
    $store = credentialStore();
    $store->store('https://a.test', ['access_token' => 'abc', 'refresh_token' => 'ref', 'expires_in' => 3600]);

    $token = $store->forUrl('https://a.test');

    expect($token['access_token'])->toBe('abc')
        ->and($token['refresh_token'])->toBe('ref')
        ->and($token['token_type'])->toBe('Bearer')
        ->and($token['expires_at'])->toBeGreaterThan(time());
});

it('stores credentials without expiry or refresh token', function () {
    $store = credentialStore();
    $store->store('https://a.test', ['access_token' => 'abc']);

    $token = $store->forUrl('https://a.test');

    expect($token['expires_at'])->toBeNull()
        ->and($token['refresh_token'])->toBeNull();
});

it('returns null when there is no credential for a url', function () {
    expect(credentialStore()->forUrl('https://missing.test'))->toBeNull();
});

it('returns null when a stored entry is not a usable token', function () {
    (new Filesystem)->put($this->credPath, json_encode([
        'https://scalar.test' => 'just-a-string',
        'https://nokey.test' => ['token_type' => 'Bearer'],
    ]));

    $store = credentialStore();

    expect($store->forUrl('https://scalar.test'))->toBeNull()
        ->and($store->forUrl('https://nokey.test'))->toBeNull();
});

it('forgets a stored credential', function () {
    $store = credentialStore();
    $store->store('https://a.test', ['access_token' => 'abc']);
    $store->forget('https://a.test');

    expect($store->forUrl('https://a.test'))->toBeNull();
});

it('falls back to the default credentials path when none is configured', function () {
    config(['laradocs.deploy.credentials' => null]);

    expect(credentialStore()->path())->toBe(storage_path('laradocs/credentials.json'));
});

/*
|--------------------------------------------------------------------------
| LocalDocs
|--------------------------------------------------------------------------
*/

function localDocs(): LocalDocs
{
    return new LocalDocs(new Filesystem);
}

it('reads an empty map when the docs directory is missing', function () {
    config(['laradocs.docs.path' => sys_get_temp_dir() . '/laradocs-missing-' . bin2hex(random_bytes(5))]);

    expect(localDocs()->read())->toBe([])
        ->and(localDocs()->isEmpty())->toBeTrue();
});

it('reads markdown files and skips other extensions', function () {
    $root = sys_get_temp_dir() . '/laradocs-read-' . bin2hex(random_bytes(5));
    $this->tempDocs[] = $root;
    $fs = new Filesystem;
    $fs->ensureDirectoryExists($root . '/guide');
    $fs->put($root . '/index.md', '# Index');
    $fs->put($root . '/guide/intro.markdown', '# Intro');
    $fs->put($root . '/notes.txt', 'skip me');
    config(['laradocs.docs.path' => $root, 'laradocs.docs.extensions' => ['md', 'markdown']]);

    expect(localDocs()->read())->toBe([
        'guide/intro.markdown' => '# Intro',
        'index.md' => '# Index',
    ])->and(localDocs()->isEmpty())->toBeFalse();
});

it('writes files and refuses to escape the docs directory', function () {
    $root = sys_get_temp_dir() . '/laradocs-write-' . bin2hex(random_bytes(5));
    $this->tempDocs[] = $root;
    config(['laradocs.docs.path' => $root]);

    $written = localDocs()->write([
        'index.md' => '# Index',
        'nested/page.md' => '# Nested',
        '../escape.md' => 'no',
        '' => 'no',
    ]);

    expect($written)->toBe(['index.md', 'nested/page.md'])
        ->and(file_get_contents($root . '/nested/page.md'))->toBe('# Nested');
});

/*
|--------------------------------------------------------------------------
| LoopbackServer
|--------------------------------------------------------------------------
*/

it('captures a callback over a real loopback socket', function () {
    $server = LoopbackServer::start(0);
    expect($server->port())->toBeGreaterThan(0);

    $client = stream_socket_client('tcp://127.0.0.1:' . $server->port(), $errno, $errstr, 2);
    fwrite($client, "GET /callback?code=abc&state=xyz HTTP/1.1\r\nHost: localhost\r\n\r\n");

    $query = $server->awaitCallback(2);

    fclose($client);
    $server->close();

    expect($query)->toBe(['code' => 'abc', 'state' => 'xyz']);
});

it('throws when the loopback port cannot be bound', function () {
    $first = LoopbackServer::start(0);

    try {
        expect(fn () => LoopbackServer::start($first->port()))->toThrow(RuntimeException::class);
    } finally {
        $first->close();
    }
});

it('throws when no callback arrives before the timeout', function () {
    $server = LoopbackServer::start(0);

    try {
        expect(fn () => $server->awaitCallback(0))->toThrow(RuntimeException::class);
    } finally {
        $server->close();
    }
});

/*
|--------------------------------------------------------------------------
| OAuthFlow
|--------------------------------------------------------------------------
*/

it('maps the browser command per operating system', function () {
    $flow = new OAuthFlow;

    expect($flow->browserCommand('Darwin'))->toBe('open')
        ->and($flow->browserCommand('Windows'))->toBe('start ""')
        ->and($flow->browserCommand('Linux'))->toBe('xdg-open');
});

it('builds a browser command containing the escaped url', function () {
    $captured = null;

    $flow = new class($captured) extends OAuthFlow
    {
        public function __construct(public ?string &$captured) {}

        protected function exec(string $command): void
        {
            $this->captured = $command;
        }

        public function openNow(string $url): void
        {
            $this->openBrowser($url);
        }
    };

    $flow->openNow('https://laradocs.dev/oauth/authorize?x=1');

    expect($captured)->toContain('laradocs.dev')
        ->and($captured)->toContain($flow->browserCommand(PHP_OS_FAMILY));
});

it('runs the real exec seam without error', function () {
    $flow = new class extends OAuthFlow
    {
        public function execNow(string $command): void
        {
            $this->exec($command);
        }
    };

    $flow->execNow('true');
})->throwsNoExceptions();

it('guards the callback state and presence of a code', function () {
    $flow = new class extends OAuthFlow
    {
        /** @param array<string,string> $q */
        public function guard(string $state, array $q): void
        {
            $this->guardCallback($state, $q);
        }

        /** @param array<string,string> $q */
        public function code(array $q): string
        {
            return $this->codeFrom($q);
        }
    };

    $flow->guard('s', ['state' => 's', 'code' => 'c']);
    expect($flow->code(['code' => 'c']))->toBe('c');

    expect(fn () => $flow->guard('s', ['error' => 'access_denied']))->toThrow(RuntimeException::class);
    expect(fn () => $flow->guard('s', ['state' => 'other']))->toThrow(RuntimeException::class);
    expect(fn () => $flow->guard('s', []))->toThrow(RuntimeException::class);
    expect(fn () => $flow->code([]))->toThrow(RuntimeException::class);
    expect(fn () => $flow->code(['code' => '']))->toThrow(RuntimeException::class);
});

it('refreshes a token', function () {
    config(['laradocs.deploy.url' => 'https://refresh.test', 'laradocs.deploy.client_id' => 'cid']);

    Http::fake(['https://refresh.test/oauth/token' => Http::response(['access_token' => 'new'])]);

    expect((new OAuthFlow)->refresh('rt')['access_token'])->toBe('new');
});

it('reports a refresh failure', function () {
    config(['laradocs.deploy.url' => 'https://refresh.test', 'laradocs.deploy.client_id' => 'cid']);

    Http::fake(['https://refresh.test/oauth/token' => Http::response([], 400)]);

    expect(fn () => (new OAuthFlow)->refresh('rt'))->toThrow(RuntimeException::class);
});

it('completes login end to end over a real loopback socket', function () {
    $port = freePort();
    config([
        'laradocs.deploy.url' => 'https://login.test',
        'laradocs.deploy.client_id' => 'cid',
        'laradocs.deploy.redirect_port' => $port,
    ]);

    Http::fake([
        'https://login.test/oauth/token' => Http::response(['access_token' => 'tok', 'refresh_token' => 'ref', 'expires_in' => 3600]),
    ]);

    $flow = new class($port) extends OAuthFlow
    {
        public function __construct(public int $port) {}

        // Stand in for the browser: connect to the loopback and deliver the code.
        protected function openBrowser(string $url): void
        {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
            $client = stream_socket_client('tcp://127.0.0.1:' . $this->port, $errno, $errstr, 2);
            fwrite($client, "GET /callback?code=the-code&state={$q['state']} HTTP/1.1\r\nHost: localhost\r\n\r\n");
            fclose($client);
        }
    };

    $prompted = null;
    $token = $flow->login(function (string $url) use (&$prompted) {
        $prompted = $url;
    });

    expect($token['access_token'])->toBe('tok')
        ->and($prompted)->toContain('https://login.test/oauth/authorize');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/oauth/token')
        && $request['grant_type'] === 'authorization_code'
        && $request['code'] === 'the-code');
});

it('throws when the token exchange fails during login', function () {
    $port = freePort();
    config([
        'laradocs.deploy.url' => 'https://login.test',
        'laradocs.deploy.client_id' => 'cid',
        'laradocs.deploy.redirect_port' => $port,
    ]);

    Http::fake(['https://login.test/oauth/token' => Http::response('nope', 400)]);

    $flow = new class($port) extends OAuthFlow
    {
        public function __construct(public int $port) {}

        protected function openBrowser(string $url): void
        {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
            $client = stream_socket_client('tcp://127.0.0.1:' . $this->port, $errno, $errstr, 2);
            fwrite($client, "GET /callback?code=c&state={$q['state']} HTTP/1.1\r\n\r\n");
            fclose($client);
        }
    };

    expect(fn () => $flow->login())->toThrow(RuntimeException::class);
});

it('rejects a mismatched state returned from authorization', function () {
    config(['laradocs.deploy.url' => 'https://login.test', 'laradocs.deploy.client_id' => 'cid']);

    $flow = new class extends OAuthFlow
    {
        protected function awaitAuthorization(string $authorizeUrl): array
        {
            return ['code' => 'c', 'state' => 'tampered'];
        }
    };

    expect(fn () => $flow->login())->toThrow(RuntimeException::class);
});

function freePort(): int
{
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $name = (string) stream_socket_get_name($socket, false);
    fclose($socket);

    return (int) substr($name, (int) strrpos($name, ':') + 1);
}

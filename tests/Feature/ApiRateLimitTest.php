<?php

declare(strict_types=1);

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Laradocs\Facades\Laradocs;
use Laradocs\Support\RateLimiterConfig;

beforeEach(function () {
    $this->makeDocs([
        'guide/intro.md' => "---\ntitle: Intro\n---\nbody\n",
        'guide/install.md' => "---\ntitle: Install\n---\nRun composer require.\n",
    ]);
});

// ─── Default behaviour ────────────────────────────────────────────────────────

it('tree endpoint allows requests within the rate limit', function () {
    Laradocs::rateLimit(3);

    $this->getJson('/docs/_laradocs/api/tree')->assertOk();
    $this->getJson('/docs/_laradocs/api/tree')->assertOk();
    $this->getJson('/docs/_laradocs/api/tree')->assertOk();
});

it('tree endpoint returns 429 once the rate limit is exceeded', function () {
    Laradocs::rateLimit(2);

    $this->getJson('/docs/_laradocs/api/tree')->assertOk();
    $this->getJson('/docs/_laradocs/api/tree')->assertOk();
    $this->getJson('/docs/_laradocs/api/tree')->assertStatus(429);
});

it('search endpoint returns 429 once the rate limit is exceeded', function () {
    Laradocs::rateLimit(2);

    $this->getJson('/docs/_laradocs/api/search?q=composer')->assertOk();
    $this->getJson('/docs/_laradocs/api/search?q=composer')->assertOk();
    $this->getJson('/docs/_laradocs/api/search?q=composer')->assertStatus(429);
});

it('tree and search share the same rate limit budget', function () {
    Laradocs::rateLimit(2);

    $this->getJson('/docs/_laradocs/api/tree')->assertOk();
    $this->getJson('/docs/_laradocs/api/search?q=composer')->assertOk();
    $this->getJson('/docs/_laradocs/api/tree')->assertStatus(429);
});

// ─── Rate limit response headers ─────────────────────────────────────────────

it('sets X-RateLimit-Limit and X-RateLimit-Remaining headers', function () {
    Laradocs::rateLimit(5);

    $response = $this->getJson('/docs/_laradocs/api/tree')->assertOk();

    expect($response->headers->get('X-RateLimit-Limit'))->toBe('5')
        ->and($response->headers->get('X-RateLimit-Remaining'))->toBe('4');
});

it('decrements X-RateLimit-Remaining on successive requests', function () {
    Laradocs::rateLimit(5);

    $this->getJson('/docs/_laradocs/api/tree');
    $response = $this->getJson('/docs/_laradocs/api/tree')->assertOk();

    expect($response->headers->get('X-RateLimit-Remaining'))->toBe('3');
});

it('429 response includes a Retry-After header', function () {
    Laradocs::rateLimit(1);

    $this->getJson('/docs/_laradocs/api/tree')->assertOk();
    $response = $this->getJson('/docs/_laradocs/api/tree')->assertStatus(429);

    expect($response->headers->has('Retry-After'))->toBeTrue();
});

// ─── Disabling rate limiting ──────────────────────────────────────────────────

it('disabling rate limiting allows unlimited requests', function () {
    Laradocs::rateLimit(false);

    foreach (range(1, 10) as $i) {
        $this->getJson('/docs/_laradocs/api/tree')->assertOk();
    }
});

it('disabled rate limiting omits rate limit headers', function () {
    Laradocs::rateLimit(false);

    $response = $this->getJson('/docs/_laradocs/api/tree')->assertOk();

    expect($response->headers->has('X-RateLimit-Limit'))->toBeFalse();
});

it('the named limiter callback yields an unlimited limit when disabled', function () {
    // The middleware short-circuits before the named limiter runs, so exercise
    // the callback directly to prove it honours rateLimit(false) defensively.
    Laradocs::rateLimit(false);

    $limiter = RateLimiter::limiter('laradocs-api');
    $limit = $limiter(Request::create('/docs/_laradocs/api/tree'));

    expect($limit)->toBeInstanceOf(Unlimited::class);
});

// ─── Custom resolver closure ──────────────────────────────────────────────────

it('honours a custom closure resolver', function () {
    Laradocs::rateLimit(fn () => Limit::perMinute(2)->by('test-consumer'));

    $this->getJson('/docs/_laradocs/api/tree')->assertOk();
    $this->getJson('/docs/_laradocs/api/tree')->assertOk();
    $this->getJson('/docs/_laradocs/api/tree')->assertStatus(429);
});

// ─── Regular docs routes are unaffected ──────────────────────────────────────

it('regular doc pages are not rate limited', function () {
    Laradocs::rateLimit(1);

    // Exhaust the API budget.
    $this->getJson('/docs/_laradocs/api/tree')->assertOk();
    $this->getJson('/docs/_laradocs/api/tree')->assertStatus(429);

    // Doc pages must still be reachable.
    $this->get('/docs/guide/intro')->assertOk();
    $this->get('/docs/guide/intro')->assertOk();
});

// ─── Config-driven default ────────────────────────────────────────────────────

it('reads the default rate limit from config when no facade override is set', function () {
    config()->set('laradocs.api.rate_limit', 2);

    // Forget the singleton so a fresh instance reads the updated config.
    app()->forgetInstance(RateLimiterConfig::class);

    $this->getJson('/docs/_laradocs/api/tree')->assertOk();
    $this->getJson('/docs/_laradocs/api/tree')->assertOk();
    $this->getJson('/docs/_laradocs/api/tree')->assertStatus(429);
});

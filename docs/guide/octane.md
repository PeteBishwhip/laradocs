---
title: Laravel Octane
description: Running Laradocs on a long-lived worker process with Laravel Octane or RoadRunner.
order: 90
---

# Laravel Octane

Laradocs is designed to work correctly on long-lived workers such as
[Laravel Octane](https://laravel.com/docs/octane) (Swoole / FrankenPHP /
RoadRunner). This page documents the singleton behaviour of the package and
what you need to know to avoid state leaking between requests.

## How Octane affects package singletons

Under classic PHP-FPM, the entire process — including the Laravel service
container — is destroyed after every request. Under Octane, a single worker
process handles many requests without restarting, so anything registered as a
**singleton** in the container persists across requests. A value written to a
singleton during request A is still there when request B runs unless it is
explicitly reset.

Laradocs registers the following classes as singletons:

| Class | Mutable? | Notes |
|---|---|---|
| `VariableRegistry` | Yes | Boot-time config only — see below |
| `MacroRegistry` | Yes | Boot-time config only — see below |
| `RateLimiterConfig` | Yes | Holds app-wide rate-limit config; resolver called per-request — safe |
| `SlugResolver` | No | Config-derived, immutable after construction |
| `MetadataResolver` | No | Stateless YAML parser |
| `DocumentParser` | No | Stateless; all constructor properties are `readonly` |
| `SeoFactory` | Yes | `$lastXCard` is per-request scratch; reset automatically — see below |
| `SearchManager` | No | Lazily resolves the configured engine once; config-derived |
| `VersionRegistry` | No | Delegates to the external cache store; no in-memory state |
| `IconRegistry` | No | Immutable after construction |

`DocumentLoader` and `DocumentCache` are **bound** (not singletons), so the
container creates a fresh instance on every resolve. They are inherently safe.

## Boot-time-only APIs

`VariableRegistry` and `MacroRegistry` are shared singletons that accumulate
state via `set()` / `register()` / `register()`. Because they persist across
requests on a long-lived worker, **you must only call the following facade
methods from a service provider's `boot()` method**:

```php
Laradocs::share('key', $value);             // VariableRegistry::set()
Laradocs::variables(['key' => $value]);     // VariableRegistry::register()
Laradocs::variables(fn () => [...]);        // closure form — safe anywhere
Laradocs::macro('name', $handler);         // MacroRegistry::register()
Laradocs::rateLimit(120);                  // RateLimiterConfig::set()
```

Calling these in a controller, middleware, or job would mutate the singleton
and the mutation would survive into the next request.

### Per-request variables via closures

When you need a variable whose value depends on the current request — for
example, the authenticated user's name — register a **closure** instead of an
eager value. Closures stored in `VariableRegistry` are re-invoked every time
`all()` is called, so they always reflect the current request state:

```php
// app/Providers/AppServiceProvider.php
use Laradocs\Facades\Laradocs;

public function boot(): void
{
    // Safe on Octane — re-evaluated fresh for every request.
    Laradocs::variables(fn () => [
        'user' => auth()->user()?->name ?? 'Guest',
    ]);
}
```

## SeoFactory and `$lastXCard`

`SeoFactory` is a singleton that tracks the X (Twitter) card type resolved
during the most recent `forDocument()` / `forPage()` call in the
`$lastXCard` property. The controller always calls `forDocument()` before
reading `xCard()`, so the value is always fresh within a request.

As a defensive measure, Laradocs registers a listener for Octane's
`RequestReceived` event that resets `$lastXCard` to the safe default
(`summary_large_image`) at the start of every new request. The listener is
only registered when `laravel/octane` is installed, so the package runs
unchanged in standard FPM environments.

## Locale restoration

The `SetDocsLocale` middleware calls `app()->setLocale()` before the response
is rendered and restores the previous locale in a `finally` block after the
response is sent. This means a French-language docs request does not leave the
worker's global locale set to `fr`. See [Localisation](/docs/guide/localisation)
for details.

## Summary

Running Laradocs on Octane requires no special configuration. The package is
designed to be Octane-safe out of the box, with one constraint on user-facing
code: **call the facade's configuration methods (`share`, `variables`,
`macro`, `rateLimit`) only from service provider `boot()` methods**, not from
request-handling code.

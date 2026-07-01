---
title: HTTP API
description: HTTP endpoints for programmatic access to the navigation tree and search index.
order: 12
---

# HTTP API

Laradocs exposes two read-only HTTP endpoints for external consumers — indexers,
MCP servers, and headless frontends. Both live under `{prefix}/_laradocs/api/`
and comply with [JSON:API 1.0](https://jsonapi.org/).

## Endpoints

| Endpoint | Description |
|---|---|
| [`GET …/api/tree`](/docs/http-api/tree) | Full navigation tree as a compound document. |
| [`GET …/api/search`](/docs/http-api/search) | Full-text search over indexed pages. |

## Common behaviour

**Authentication.** The endpoints are guarded by the same middleware stack as
the rest of your docs. Add auth middleware via `route.middleware` in
`config/laradocs.php` to restrict access.

**Enabled flag.** Both endpoints return `404` when `laradocs.enabled` is
`false` — the same as all other docs routes.

**Route prefix.** The URL prefix is whatever you've configured in
`route.prefix` (default `docs`). All examples in this reference use `/docs`.

**Content type.** Every response is served with
`Content-Type: application/vnd.api+json`.

**Envelope.** Every response shares this top-level shape:

```json
{
  "jsonapi": { "version": "1.0" },
  "links":   { "self": "https://example.com/docs/_laradocs/api/…" },
  "data":    []
}
```

## Rate limiting

Both endpoints are rate limited to **60 requests per minute per IP** by default.

### Response headers

Every non-throttled response includes:

| Header | Description |
|---|---|
| `X-RateLimit-Limit` | The configured per-minute ceiling. |
| `X-RateLimit-Remaining` | Requests remaining in the current window. |

A `429 Too Many Requests` response adds:

| Header | Description |
|---|---|
| `Retry-After` | Seconds until the rate limit resets. |

### Configuring the limit

Set `LARADOCS_API_RATE_LIMIT` in your environment, or publish the config and
edit `api.rate_limit` directly:

```php
// config/laradocs.php
'api' => [
    'rate_limit' => 120,
],
```

### Overriding or disabling via the facade

For programmatic control, call `Laradocs::rateLimit()` in a service provider's
`boot()` method. Changes take effect on every subsequent request.

```php
use Laradocs\Facades\Laradocs;
use Illuminate\Cache\RateLimiting\Limit;

// Disable rate limiting entirely.
Laradocs::rateLimit(false);

// Raise the limit to 120 rpm.
Laradocs::rateLimit(120);

// Full control — return any Limit object.
Laradocs::rateLimit(function ($request): Limit {
    return Limit::perMinute(
        $request->user()?->isSubscriber() ? 300 : 60
    )->by($request->user()?->id ?: $request->ip());
});
```

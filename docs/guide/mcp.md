---
title: MCP Server
description: Expose your docs to AI assistants via the Model Context Protocol (MCP).
group: Guide
---

# MCP Server

Laradocs can expose your documentation as a **Model Context Protocol (MCP)
server**, letting AI assistants like Claude, Cursor, and any MCP-compatible
tool read, search, and browse your docs without manual copy-pasting.

## Enabling

Set `LARADOCS_MCP=true` in your `.env` file and optionally verify the endpoint
is reachable:

```env
LARADOCS_MCP=true
```

The endpoint lives at `{prefix}/mcp` (default: `/docs/mcp`) and uses HTTP
method to serve two audiences from the same URL:

- **`GET /docs/mcp`** — renders this page as a normal documentation page in the
  browser, provided `mcp.md` (or `docs/mcp.md`) exists in your content directory.
- **`POST /docs/mcp`** — the MCP JSON-RPC server, consumed by AI assistants.

## Connecting an AI assistant

Add the server to your MCP client configuration:

```json
{
  "mcpServers": {
    "laradocs": {
      "type": "http",
      "url": "https://your-app.example.com/docs/mcp"
    }
  }
}
```

The server advertises three tools:

| Tool | Description |
|---|---|
| `search_docs` | Full-text search across the docs, ranked by relevance. |
| `list_pages` | Enumerate every visible page, optionally filtered by group. |
| `fetch_page` | Fetch the full markdown and metadata of a single page by slug. |

## Authentication

The MCP endpoint is **open by default** — no token is required. This is the
right choice when your docs site is already public.

If you want to restrict access, set `LARADOCS_MCP_AUTH_GUARD` to any Laravel
[authentication guard](https://laravel.com/docs/authentication#adding-custom-guards)
name. Requests that fail the guard's check receive a `401 Unauthorized`
response before the JSON-RPC layer is reached:

```env
# Require a valid token via the "api" guard (Laravel Passport / Sanctum)
LARADOCS_MCP_AUTH_GUARD=api
```

You can also set this in code, for example to enable auth conditionally:

```php
// config/laradocs.php
'mcp' => [
    'enabled' => env('LARADOCS_MCP', false),
    'auth' => [
        'guard' => env('LARADOCS_MCP_AUTH_GUARD'),
    ],
],
```

### Using Laravel Passport (OAuth 2.0)

[Laravel Passport](https://laravel.com/docs/passport) gives you a full OAuth
2.0 server. The Client Credentials grant is the right fit for machine-to-machine
access like MCP clients.

**1 — Install Passport**

```bash
composer require laravel/passport
php artisan passport:install
```

**2 — Register the guard**

`config/auth.php`:

```php
'guards' => [
    'api' => [
        'driver'   => 'passport',
        'provider' => 'users',
    ],
],
```

**3 — Enable the Client Credentials grant**

Add `CheckClientCredentials` to your API middleware or apply it globally
via `Passport::enableImplicitGrant()`. Alternatively, register it for just
the MCP route by binding a custom guard that wraps the check — see the
[Passport docs](https://laravel.com/docs/passport#client-credentials-grant-tokens)
for the full setup.

**4 — Point Laradocs at the guard**

```env
LARADOCS_MCP_AUTH_GUARD=api
```

**5 — Issue a client-credentials token**

```bash
php artisan passport:client --client
# Note the client id and secret
```

Then obtain a token:

```bash
curl -s -X POST https://your-app.example.com/oauth/token \
  -d grant_type=client_credentials \
  -d client_id=YOUR_CLIENT_ID \
  -d client_secret=YOUR_CLIENT_SECRET \
  -d scope=""
```

**6 — Configure your MCP client**

```json
{
  "mcpServers": {
    "laradocs": {
      "type": "http",
      "url": "https://your-app.example.com/docs/mcp",
      "headers": {
        "Authorization": "Bearer YOUR_ACCESS_TOKEN"
      }
    }
  }
}
```

### Using Laravel Sanctum (API tokens)

Sanctum's token guard works the same way — set the guard to `sanctum` (or
whatever you've configured) and issue a token with
`$user->createToken('mcp')->plainTextToken`. Pass it as a `Bearer` header in
the client configuration shown above.

### Writing a custom guard

If neither Passport nor Sanctum fits, register a guard in
`AuthServiceProvider::boot()`:

```php
Auth::extend('mcp-hmac', function ($app, $name, array $config) {
    return new \App\Auth\HmacGuard(
        $app['request'],
        secret: config('services.mcp_secret'),
    );
});
```

Then set `LARADOCS_MCP_AUTH_GUARD=mcp-hmac`.

## Rate limiting

The MCP endpoint shares the standard Laradocs API rate limit
(`LARADOCS_API_RATE_LIMIT`, default 60 requests per minute per IP). Increase
it if your AI assistant issues many tool calls in quick succession:

```env
LARADOCS_API_RATE_LIMIT=300
```

---
title: MCP Server
description: Expose your docs to AI assistants via the Model Context Protocol (MCP).
order: 1
---

# MCP Server

Laradocs can expose your documentation as a **Model Context Protocol (MCP)
server**, letting AI assistants like Claude, Cursor, and any MCP-compatible
tool read, search, and browse your docs without manual copy-pasting.

## Enabling

Set `LARADOCS_MCP=true` in your `.env` file:

```env
LARADOCS_MCP=true
```

The endpoint lives at `{prefix}/mcp` (default: `/docs/mcp`) and uses the HTTP
method to serve two audiences from the same URL:

- **`GET /docs/mcp`** — renders this page as a normal documentation page in the
  browser, provided `mcp.md` exists in your content directory.
- **`POST /docs/mcp`** — the MCP JSON-RPC server consumed by AI assistants.

## Available tools

The server advertises three **read-only** tools. No tool can create, modify, or
delete content — the MCP endpoint is a read window into your docs, nothing more.

| Tool | Description |
|---|---|
| `search_docs` | Full-text search across the docs, ranked by relevance. |
| `list_pages` | Enumerate every visible page, optionally filtered by group. |
| `fetch_page` | Fetch the full markdown and metadata of a single page by slug. |

## Connecting a client

The sections below give a complete, copy-paste config for each supported client.
Replace `https://your-app.example.com` with your actual site URL.

### Claude Code

Claude Code reads MCP server configuration from `~/.claude/settings.json`
(global) or `.claude/settings.json` at the project root (project-scoped).

**Global config** — available in every Claude Code session:

```json
// ~/.claude/settings.json
{
  "mcpServers": {
    "laradocs": {
      "type": "http",
      "url": "https://your-app.example.com/docs/mcp"
    }
  }
}
```

**Project-scoped config** — checked into the repository so your whole team gets
it automatically:

```json
// .claude/settings.json  (commit this file)
{
  "mcpServers": {
    "laradocs": {
      "type": "http",
      "url": "https://your-app.example.com/docs/mcp"
    }
  }
}
```

After saving, restart Claude Code (or run `/mcp` in a session) to pick up the
new server.

### Claude Desktop

Claude Desktop stores its config in a platform-specific location:

- **macOS:** `~/Library/Application Support/Claude/claude_desktop_config.json`
- **Windows:** `%APPDATA%\Claude\claude_desktop_config.json`

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

Save the file and restart Claude Desktop. The server will appear under
**Settings → MCP Servers**.

### Cursor

Cursor reads MCP config from `~/.cursor/mcp.json` (global) or
`.cursor/mcp.json` at the workspace root (workspace-scoped).

```json
// ~/.cursor/mcp.json  or  .cursor/mcp.json
{
  "mcpServers": {
    "laradocs": {
      "url": "https://your-app.example.com/docs/mcp"
    }
  }
}
```

After saving, open **Cursor Settings → MCP** and confirm the server status
shows a green indicator.

## Locking it down with auth

### Security trade-offs

The MCP endpoint is **open by default** — no token is required. This is the
right choice when your docs are already publicly accessible. Because all three
tools are read-only, there is **no write risk**: the worst an unauthenticated
request can do is read content that is already public.

Add authentication when:

- Your docs site sits behind a login wall (e.g. internal product docs).
- You want to limit MCP access to specific CI systems or team members even
  though the docs are otherwise public.
- Compliance or audit requirements demand a traceable access trail.

### Enabling auth

Set `LARADOCS_MCP_AUTH_GUARD` to any Laravel
[authentication guard](https://laravel.com/docs/authentication#adding-custom-guards)
name. Requests that fail the guard's check receive a `401 Unauthorized` JSON
response before the JSON-RPC layer is reached:

```env
# Require a valid token via the "api" guard (Laravel Passport or Sanctum)
LARADOCS_MCP_AUTH_GUARD=api
```

### Laravel Sanctum (recommended for most apps)

Sanctum API tokens are the simplest option when you already use Sanctum for
your application's own token auth.

**1 — Install Sanctum (skip if already installed)**

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

**2 — Register the Sanctum guard**

`config/auth.php`:

```php
'guards' => [
    'sanctum' => [
        'driver'   => 'sanctum',
        'provider' => 'users',
    ],
],
```

**3 — Point Laradocs at the guard**

```env
LARADOCS_MCP_AUTH_GUARD=sanctum
```

**4 — Issue a token**

```php
// In a tinker session or a dedicated Artisan command:
$token = \App\Models\User::first()->createToken('mcp-access')->plainTextToken;
echo $token;
```

**5 — Configure your MCP client**

Add the `Authorization` header to whichever client you're using.

*Claude Code (`~/.claude/settings.json` or `.claude/settings.json`):*

```json
{
  "mcpServers": {
    "laradocs": {
      "type": "http",
      "url": "https://your-app.example.com/docs/mcp",
      "headers": {
        "Authorization": "Bearer YOUR_SANCTUM_TOKEN"
      }
    }
  }
}
```

*Claude Desktop (`claude_desktop_config.json`):*

```json
{
  "mcpServers": {
    "laradocs": {
      "type": "http",
      "url": "https://your-app.example.com/docs/mcp",
      "headers": {
        "Authorization": "Bearer YOUR_SANCTUM_TOKEN"
      }
    }
  }
}
```

*Cursor (`.cursor/mcp.json`):*

```json
{
  "mcpServers": {
    "laradocs": {
      "url": "https://your-app.example.com/docs/mcp",
      "headers": {
        "Authorization": "Bearer YOUR_SANCTUM_TOKEN"
      }
    }
  }
}
```

### Laravel Passport (OAuth 2.0)

[Laravel Passport](https://laravel.com/docs/passport) is the right choice when
you need short-lived tokens, token rotation, or a proper OAuth 2.0 flow. The
**Client Credentials** grant is best for machine-to-machine access like MCP
clients, because it does not require a user login.

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

Add `CheckClientCredentials` middleware to the `api` middleware group in
`bootstrap/app.php`, or apply it to just the MCP route via a custom guard that
wraps the check. See the
[Passport docs](https://laravel.com/docs/passport#client-credentials-grant-tokens)
for the full setup.

**4 — Point Laradocs at the guard**

```env
LARADOCS_MCP_AUTH_GUARD=api
```

**5 — Create a client and obtain a token**

```bash
php artisan passport:client --client
# Note the client id and secret printed to the terminal
```

```bash
curl -s -X POST https://your-app.example.com/oauth/token \
  -d grant_type=client_credentials \
  -d client_id=YOUR_CLIENT_ID \
  -d client_secret=YOUR_CLIENT_SECRET \
  -d scope=""
# Returns {"access_token":"...", "expires_in":...}
```

**6 — Configure your MCP client**

Use the `access_token` from the response above as the `Bearer` value in the
`Authorization` header — same header format as the Sanctum examples above.
Rotate the token before `expires_in` seconds have elapsed by repeating
step 5.

### Custom guards

If neither Passport nor Sanctum fits (for example, you want HMAC signature
verification), register a guard in `AuthServiceProvider::boot()`:

```php
Auth::extend('mcp-hmac', function ($app, $name, array $config) {
    return new \App\Auth\HmacGuard(
        $app['request'],
        secret: config('services.mcp_secret'),
    );
});
```

Then set `LARADOCS_MCP_AUTH_GUARD=mcp-hmac`. The guard only needs to implement
`check()` — Laradocs does not call `user()` or `login()`.

## Rate limiting

The MCP endpoint shares the standard Laradocs API rate limit
(`LARADOCS_API_RATE_LIMIT`, default 60 requests per minute per IP). Increase
it if your AI assistant issues many tool calls in quick succession:

```env
LARADOCS_API_RATE_LIMIT=300
```

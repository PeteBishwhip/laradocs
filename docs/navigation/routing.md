---
title: Routing
description: How file paths and metadata become URLs.
order: 1
---

# Routing

Laradocs generates a URL for every document. The strategy is configurable
via `laradocs.routing.strategy`:

- **`filename`** — the slug comes from the file path only.
- **`metadata`** — the slug comes from the front-matter `slug:`
  (falling back to `routing.fallback`, default `filename`).
- **`both`** (default) — metadata wins when present, otherwise the
  filename.

## Filename slugs

```text
docs/guide/getting-started.md   ->  /docs/guide/getting-started
docs/http-api/_index.md         ->  /docs/http-api
docs/_index.md                  ->  /docs
```

A file named `_index.md` inside a folder is the section landing page;
that filename is configurable via `docs.index`.

## Overriding with metadata

```markdown
---
title: Authentication
slug: security/auth
---
```

This page is served at `/docs/security/auth` regardless of where the
file lives on disk. Path-traversal slugs (`../foo`) are neutralised
before resolution.

## Redirects

Set `redirect:` in front-matter to bounce visitors elsewhere — handy
when you move or rename a page:

```markdown
---
redirect: guide/routing
---
```

```markdown
---
redirect: https://example.com/docs/new
---
```

Relative values resolve through `route('laradocs.show', …)` so they
honour your `route.prefix` and `route.domain`. Absolute URLs are
allowed through the URL safety guard (no `javascript:` or `data:`).

## Route prefix and domain

```dotenv
LARADOCS_ROUTE_PREFIX=docs
LARADOCS_ROUTE_DOMAIN=docs.example.com
```

`LARADOCS_ROUTE_PREFIX=""` mounts the docs at the application root —
useful for dedicated docs sites — but be careful to register the
package after your application's other routes so it doesn't shadow
them.

> [!WARNING]
> Changing the prefix or domain requires `php artisan route:clear` if
> you cache routes; both values are read at boot.

## Middleware

```php
// config/laradocs.php
'route' => [
    'middleware' => ['web', 'auth'],
],
```

Any middleware alias or FQCN works. Use this to gate internal docs
behind an authenticated guard.

### Package middleware

The package appends its own middleware after your `middleware` list. By
default that stack is:

```php
'package_middleware' => [
    \Laradocs\Http\Middleware\EnsureDocsEnabled::class,
    \Laradocs\Http\Middleware\SetDocsLocale::class,
    \Laradocs\Http\Middleware\SetDocsVersion::class,
],
```

| Middleware | Purpose |
|---|---|
| `EnsureDocsEnabled` | Returns `404` when `laradocs.enabled` is `false`. |
| `SetDocsLocale` | Resolves the active locale from the URL, cookie, or `Accept-Language` header. |
| `SetDocsVersion` | Resolves the active version and, when `unversioned` is `redirect`, redirects bare prefix requests. |

You can override `package_middleware` to reorder, replace, or extend the
built-in stack. For example, to drop locale resolution on a site that only
has one language:

```php
'package_middleware' => [
    \Laradocs\Http\Middleware\EnsureDocsEnabled::class,
    \Laradocs\Http\Middleware\SetDocsVersion::class,
],
```

Or to insert a custom middleware between the package ones:

```php
'package_middleware' => [
    \Laradocs\Http\Middleware\EnsureDocsEnabled::class,
    \App\Http\Middleware\SetDocsTheme::class,
    \Laradocs\Http\Middleware\SetDocsLocale::class,
    \Laradocs\Http\Middleware\SetDocsVersion::class,
],
```

> [!WARNING]
> Removing `EnsureDocsEnabled` means the `laradocs.enabled = false` switch no
> longer gates the docs routes. Removing `SetDocsLocale` or `SetDocsVersion`
> disables those features globally rather than just hiding the UI controls — only
> do so if you know you don't need them.

## Disabling docs

```dotenv
LARADOCS_ENABLED=false
```

When disabled, no routes are registered at all and every docs URL
returns `404` — including the asset route.

## Asset route

The package serves its compiled CSS and JS from a route under your
configured prefix:

```text
/docs/_laradocs/asset/laradocs.css
/docs/_laradocs/asset/laradocs.js
```

There is no `Cache-Control` header on this route by default — attach
your own caching middleware (or publish the assets with
`vendor:publish --tag=laradocs-assets`) if you want to cache them.

## Named routes

All routes are registered under the `laradocs.` name prefix:

| Name | Path | Purpose |
|---|---|---|
| `laradocs.index` | `/{prefix}` | The home page. |
| `laradocs.show` | `/{prefix}/{path}` | Any document by slug. |
| `laradocs.asset` | `/{prefix}/_laradocs/asset/{file}` | Bundled CSS/JS. |

Use them via `route('laradocs.show', ['path' => 'guide/routing'])`.

The prefix itself is configurable with `LARADOCS_ROUTE_NAME` (or the
`route.name` config key). The package builds its own internal links from
this value, so changing it — say to `docs.` — renames every route without
breaking navigation. Keep the trailing dot; it separates the prefix from
the route name.

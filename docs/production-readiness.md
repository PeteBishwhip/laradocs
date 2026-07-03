---
title: Production Readiness Checklist
description: A pre-launch checklist covering caching, environment variables, route caching, Lighthouse targets, and security hardening.
order: 14
---

# Production Readiness Checklist

Laradocs ships with sensible defaults for local development. Before you point
a domain at it, work through this checklist — it covers the app-level
settings every Laravel project needs plus the handful of things that are
specific to Laradocs. Everything below has been run against a stock Laravel
install with only Laradocs added.

## Environment

Start from your framework's own production baseline (`APP_ENV=production`,
`APP_DEBUG=false`, a generated `APP_KEY`, HTTPS-only `APP_URL`) then layer on
the Laradocs-specific values:

| Variable | Recommended production value | Why |
|---|---|---|
| `APP_DEBUG` | `false` | Stack traces and env values must never reach a public response. |
| `CACHE_STORE` | `redis` or `database` (not `array`) | Laradocs' render cache (below) needs a store that survives past a single request. |
| `LARADOCS_ENABLED` | `true` for public docs, `false` for internal-only builds | When `false`, every docs route — including the asset route — 404s immediately; nothing renders or leaks. |
| `LARADOCS_CACHE` | `true` | Skip re-parsing Markdown on every request. |
| `LARADOCS_CACHE_STORE` | A dedicated store (e.g. `redis`) if `CACHE_STORE` is shared with app data you flush often | Docs cache keys are prefixed (`key_prefix`), but a separate store keeps a `cache:clear` for app data from also dropping every rendered page. |
| `LARADOCS_ROUTE_PREFIX` | `docs` (or your chosen prefix) | Changing this after deploy requires `route:clear` — see [Route caching](#route-caching). |

See [Configuration](/docs/configuration) for the full option/env reference.

## Caching

Rendering Markdown to HTML, and building the search index, are both cached —
see [Caching](/docs/advanced/caching). Warm both at deploy time rather than
on the first visitor's request:

```bash
php artisan optimize
```

`optimize` hooks Laradocs into Laravel's own bootstrap cache, so one command
warms config, routes, views, events **and** the docs cache together
(confirmed against a stock install — `optimize` reports a `laradocs` step
alongside the framework's own):

```
  config ......................................................... DONE
  events ......................................................... DONE
  routes ......................................................... DONE
  views ......................................................... DONE
  laradocs ...................................................... DONE
```

Run `php artisan optimize:clear` before re-deploying, and `php artisan
laradocs:clear` on its own if you only need to drop stale rendered pages
(e.g. after editing `docs/` without a full release).

> [!WARNING]
> The compiled asset route (`{prefix}/_laradocs/asset/{file}`, serving
> `laradocs.css`/`laradocs.js`) does not send a `Cache-Control` header by
> default. Either attach your own caching middleware to it, or publish the
> assets (`vendor:publish --tag=laradocs-assets`) and let your web server or
> CDN serve them with a long, immutable cache lifetime instead.

## Route caching

Laravel's `route:cache` works out of the box — Laradocs registers its routes
unconditionally (regardless of `laradocs.enabled`) specifically so a cached
route table always includes them. Verified against a stock install:

```bash
php artisan route:cache
#   INFO  Routes cached successfully.
```

Add it to your deploy script alongside `optimize`:

```bash
php artisan route:cache
php artisan optimize
```

> [!WARNING]
> `route.prefix`, `route.domain` and `route.name` are all read once at boot.
> If you change any of them, run `php artisan route:clear` before the next
> `route:cache` — a stale cached table will keep serving the old URLs. See
> [Routing](/docs/navigation/routing).

## Lighthouse target scores

Aim for the following on a representative content page (not just the
homepage):

| Category | Target |
|---|---|
| Performance | 90+ |
| Accessibility | 100 |
| Best Practices | 100 |
| SEO | 100 |

Laradocs' defaults already push toward these: images and video embeds are
lazy-loaded (`loading="lazy"`), titles/meta descriptions/Open Graph/Twitter
cards/canonical URLs/JSON-LD are generated per page (see [SEO](/docs/seo)),
and a `sitemap.xml` and `robots.txt` are served automatically. Two things
worth tuning before you run Lighthouse for real:

- **Self-hosted webfonts.** By default the layout loads Inter and JetBrains
  Mono from Google Fonts, which costs a render-blocking third-party request.
  There is no environment variable for this — publish the config
  (`vendor:publish --tag=laradocs-config`) and set:

  ```php
  'ui' => [
      'webfonts' => false,
      'fonts' => [
          'sans' => '"Your Self-Hosted Font", sans-serif',
      ],
  ],
  ```

- **Cache the compiled asset route** as described above — Lighthouse's
  "uses long cache TTL" audit will otherwise flag it.

Run Lighthouse (or Lighthouse CI) against a built page after deploying, not
just `php artisan serve` locally — asset caching and compression from your
production web server materially affect the Performance score.

## Security hardening

Most of this is standard Laravel hardening, not specific to Laradocs — but
worth confirming explicitly before launch:

- **`APP_DEBUG=false`** in every environment reachable from the internet.
- **HTTPS only.** Terminate TLS at your load balancer or web server, and set
  `TrustProxies` / `trustProxies()` so Laravel sees the correct scheme —
  otherwise generated URLs (canonical links, sitemap entries, OG images) can
  come back as `http://`.
- **Gate non-public docs with auth middleware.** `route.middleware` defaults
  to `['web']`; add your guard for internal or staging docs:

  ```php
  // config/laradocs.php
  'route' => [
      'middleware' => ['web', 'auth'],
  ],
  ```

- **Turn docs off entirely where they shouldn't exist**, e.g. a
  customer-facing environment that isn't ready to publish yet:

  ```dotenv
  LARADOCS_ENABLED=false
  ```

  This 404s every docs route, including the asset route — nothing is served,
  not even a redirect.
- **Author-supplied links are already scheme-guarded.** Macro arguments
  (`button`, `embed`) and `redirect:` front-matter both pass through a URL
  safety check that only allows `http`, `https`, `mailto` and `tel` —
  `javascript:`/`data:` links are rejected and rendered as `#`. This is
  defense-in-depth, not a reason to accept documentation contributions from
  untrusted authors — treat `docs/` like source code and review changes to
  it the same way.
- **Laradocs sets no security headers of its own** (no
  `Content-Security-Policy`, `X-Frame-Options`, etc.) — that's your
  application's responsibility, same as for the rest of your Laravel app.

## Checklist

- [ ] `APP_DEBUG=false`, `APP_ENV=production`, HTTPS enforced end-to-end.
- [ ] `CACHE_STORE` set to a persistent store; `LARADOCS_CACHE_STORE` set
      deliberately if it should differ from your app's default.
- [ ] Deploy script runs `php artisan route:cache` and `php artisan
      optimize`.
- [ ] Compiled asset route or published assets have a long-lived
      `Cache-Control` header.
- [ ] Webfonts self-hosted (or accepted as a deliberate trade-off) before
      measuring Lighthouse Performance.
- [ ] Lighthouse run against a deployed content page meets the targets
      above.
- [ ] `route.middleware` includes your auth guard for any non-public docs;
      `LARADOCS_ENABLED=false` for environments that shouldn't serve docs at
      all.

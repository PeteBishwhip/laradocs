---
title: Configuration
description: Every Laradocs option and the environment variables that drive it.
order: 3
---

# Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=laradocs-config
```

It lands at `config/laradocs.php`. Every important value is also driven by an
environment variable so you can change behaviour per-environment without
editing PHP.

## Master switch

| Option | Env | Default |
|---|---|---|
| `enabled` | `LARADOCS_ENABLED` | `true` |

When `false`, no routes are registered and every docs URL returns `404`.

## Routes

| Option | Env | Default |
|---|---|---|
| `route.prefix` | `LARADOCS_ROUTE_PREFIX` | `docs` |
| `route.domain` | `LARADOCS_ROUTE_DOMAIN` | `null` |
| `route.middleware` | — | `['web']` |
| `route.name` | — | `laradocs.` |

`middleware` accepts any Laravel middleware aliases or class names. Use it to
gate docs behind auth in non-public projects.

> [!WARNING]
> Route prefix and domain are read at boot. After changing them, clear your
> route cache with `php artisan route:clear`.

## Documents

| Option | Env | Default |
|---|---|---|
| `docs.path` | `LARADOCS_PATH` | `base_path('docs')` |
| `docs.extensions` | — | `['md', 'markdown']` |
| `docs.ignored_patterns` | — | `['.*', '_drafts', 'README.md']` |
| `docs.index` | — | `_index` |

`ignored_patterns` uses `fnmatch` syntax. `docs.index` controls the filename
treated as a section landing page (so `guide/_index.md` serves `/docs/guide`).

## Routing strategy

| Option | Env | Default |
|---|---|---|
| `routing.strategy` | `LARADOCS_ROUTING_STRATEGY` | `both` |
| `routing.fallback` | — | `filename` |

| Strategy | Behaviour |
|---|---|
| `filename` | Slug from the file path only. |
| `metadata` | Slug from front-matter `slug:`, falling back to `routing.fallback`. |
| `both` | Front-matter `slug:` wins when present, otherwise the filename. |

## Metadata defaults

```php
'metadata' => [
    'default' => [
        'order'  => 999,
        'hidden' => false,
    ],
],
```

These supply values when a page omits them.

## Parser

| Option | Env | Default |
|---|---|---|
| `parser.extensions.gfm` | — | `true` |
| `parser.extensions.attributes` | — | `true` |
| `parser.extensions.footnotes` | — | `true` |
| `parser.extensions.callouts` | — | `true` |
| `parser.extensions.heading_anchors` | — | `true` |
| `parser.extensions.images` | — | `true` |
| `parser.extensions.video` | — | `true` |
| `parser.extensions.variables` | — | `true` |
| `parser.extensions.macros` | — | `true` |
| `parser.highlighter` | `LARADOCS_HIGHLIGHTER` | `shiki-css` |
| `parser.unknown_variable` | — | `blank` |
| `parser.toc.min_level` | — | `2` |
| `parser.toc.max_level` | — | `3` |
| `parser.toc.min_headings` | — | `2` |

`unknown_variable` controls how `{{ undefined_key }}` renders: `blank` leaves
nothing, `raw` keeps the literal braces. The TOC only renders when a page has
at least `min_headings` headings inside the level range.

See [Rich Content](/docs/features/rich-content) for what each extension enables.

## UI

| Option | Env | Default |
|---|---|---|
| `ui.theme` | `LARADOCS_THEME` | `auto` |
| `ui.preset` | `LARADOCS_UI_PRESET` | `classic` |
| `ui.accent` | `LARADOCS_ACCENT` | `#FF2D20` |
| `ui.webfonts` | — | `true` |
| `ui.fonts.sans` | `LARADOCS_FONT_SANS` | `null` |
| `ui.fonts.mono` | `LARADOCS_FONT_MONO` | `null` |
| `ui.brand.title` | `LARADOCS_TITLE` | `Documentation` |
| `ui.brand.tagline` | `LARADOCS_TAGLINE` | `null` |
| `ui.brand.logo` | `LARADOCS_LOGO` | `null` |
| `ui.brand.favicon` | `LARADOCS_FAVICON` | `null` |
| `ui.header.links` | — | `[]` |
| `ui.sidebar.collapsible` | — | `true` |
| `ui.sidebar.show_root` | — | `true` |
| `ui.footer.enabled` | `LARADOCS_FOOTER` | `true` |
| `ui.footer.text` | `LARADOCS_FOOTER_TEXT` | `null` |
| `ui.footer.links` | — | `[]` |
| `ui.edit.url` | `LARADOCS_EDIT_URL` | `null` |
| `ui.edit.label` | `LARADOCS_EDIT_LABEL` | `Edit this page` |
| `ui.search.enabled` | `LARADOCS_SEARCH` | `true` |

See [Customising the UI](/docs/customising-the-ui) for what each value does
visually.

## Analytics

| Option | Env | Default |
|---|---|---|
| `analytics.fathom.site` | `LARADOCS_FATHOM_SITE` | `null` |
| `analytics.fathom.script` | `LARADOCS_FATHOM_SCRIPT` | `https://cdn.usefathom.com/script.js` |
| `analytics.fathom.spa` | `LARADOCS_FATHOM_SPA` | `null` |
| `analytics.google.measurement_id` | `LARADOCS_GA_MEASUREMENT_ID` | `null` |
| `analytics.google.anonymize_ip` | `LARADOCS_GA_ANONYMIZE_IP` | `false` |

Set the identifier to enable a provider — leave it `null` to opt out.
See [Analytics](/docs/guide/analytics) for details.

## Cache

| Option | Env | Default |
|---|---|---|
| `cache.enabled` | `LARADOCS_CACHE` | `true` |
| `cache.store` | `LARADOCS_CACHE_STORE` | `null` (default store) |
| `cache.ttl` | `LARADOCS_CACHE_TTL` | `86400` |
| `cache.key_prefix` | — | `laradocs` |

See [Caching](/docs/guide/caching).

## Variables and macros

```php
'variables' => [
    'version' => '1.0.0',
],

'macros' => [
    // 'my-macro' => 'partials.my-macro',
],
```

See [Variables](/docs/features/variables) and [Macros](/docs/features/macros).

## Disabling docs in production

```dotenv
LARADOCS_ENABLED=false
```

All docs routes return `404` instantly — useful when shipping early builds
where the docs aren't ready for public consumption.

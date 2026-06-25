---
title: Sitemap
description: An auto-generated sitemap.xml served straight from the document tree.
order: 1
---

# Sitemap

Laradocs publishes a [sitemaps.org](https://www.sitemaps.org/protocol.html)-compliant
`sitemap.xml` for the entire docs site. Nothing to configure — drop a page
into `docs/`, and it shows up in the sitemap the moment search engines next
fetch it.

```
GET {prefix}/sitemap.xml
```

For the default prefix, that's `/docs/sitemap.xml`. Add the absolute URL to
your `robots.txt` so crawlers find it:

```
Sitemap: https://example.com/docs/sitemap.xml
```

## What's listed

Every visible documentation page, emitted in **tree order** — parents before
their children, sections in the same order they appear in the sidebar — with a
`<lastmod>` and a `<priority>`.

Two kinds of page are skipped:

- pages with `hidden: true` — never advertised;
- pages with a `redirect:` target — a sitemap should only point to canonical
  destinations, not interstitials.

The docs root (`_index.md`) is included with the docs index URL when present.

## `<lastmod>`

Taken from the page's front-matter `updated_at:` when set, otherwise from the
file's modification time on disk. Rendered as an ISO 8601 timestamp.

```markdown
---
title: Release Notes
updated_at: "2026-06-01"
---
```

> [!TIP]
> Quote the date in YAML (or use a full timestamp) to keep it as a string —
> bare dates get parsed and may emit a slightly different value.

## `<priority>`

`<priority>` is `1.0` for the docs root and falls off with depth:

| Depth | Default priority |
|---|---|
| Root (`_index.md`) | `1.0` |
| Top-level pages | `0.8` |
| One level deep | `0.6` |
| Deeper | `0.4` |

Override per page by adding a `priority:` to front-matter — any value in
`[0.0, 1.0]`:

```markdown
---
title: Marketing Landing Page
priority: 1.0
---
```

## Caching

The sitemap is cached alongside the navigation tree and the search index,
keyed on the combined mtimes of every document. Edit a file and the cache
busts automatically — there's nothing to clear during development.

`php artisan laradocs:cache` pre-renders the sitemap as part of warming the
cache, and `php artisan laradocs:clear` wipes it. See
[Caching](/docs/advanced/caching) and the [CLI](/docs/cli) for the full
flow.

---
title: Search
description: Full-text search for the command palette, with optional Scout engines.
order: 3
group: Guide
---

# Search

Press <kbd>⌘</kbd><kbd>K</kbd> (or <kbd>Ctrl</kbd><kbd>K</kbd>) anywhere in your
docs to open the command palette. Out of the box it searches page **titles and
body content**, returning ranked results with a short excerpt around the match.

Laradocs builds a pre-rendered index of every page so search stays fast and
works without any external services. If your application uses
[Laravel Scout](https://laravel.com/docs/scout), Laradocs will hand search over
to your configured engine (Meilisearch, Typesense or Algolia) automatically.

## How it works

1. `laradocs:index` (also run by `laradocs:cache`) renders every visible page,
   strips it to plain text, and stores a compact index.
2. The index is cached and keyed by file modification time, so it rebuilds
   itself whenever a document changes — and `laradocs:clear` drops it.
3. The palette queries `{prefix}/_laradocs/search?q=...`, which returns JSON
   results from whichever engine is active.

## In the palette

Results render the way you'd expect a docs search to:

- **Highlighted matches** — every query term is marked in both the title and the
  excerpt, so you can see *why* a page matched at a glance.
- **Breadcrumbs** — each hit shows the section trail it lives under (its
  `group:`, then any deeper folders), so same-named pages are easy to tell apart.
- **Grouped by section** — hits are clustered under their section heading while
  keeping rank order, so related pages sit together.

Keyboard navigation (<kbd>↑</kbd>/<kbd>↓</kbd> to move, <kbd>↵</kbd> to open)
skips the section headings and lands only on results. If JavaScript or the index
is unavailable, the palette degrades gracefully to title-only matching over the
pre-rendered page list.

## Choosing an engine

Configure the driver in `config/laradocs.php`:

```php
'search' => [
    'driver' => env('LARADOCS_SEARCH_DRIVER', 'auto'),
    'index' => env('LARADOCS_SEARCH_INDEX', 'laradocs'),
    'limit' => 20,
    'min_chars' => 2,
    'max_chars' => 10000,
],
```

| `driver` | Behaviour |
|---|---|
| `auto` | Use Scout when it's installed **and** configured, otherwise the JSON index. |
| `scout` | Force Scout (falls back to JSON if Scout isn't installed). |
| `json` | Always use the built-in, dependency-free JSON index. |

### JSON index (default)

No dependencies, no services. Laradocs ranks the cached index in-process:
title matches outrank body matches, and every word in the query must appear
somewhere on the page. Ideal for small-to-medium documentation sets.

### Laravel Scout

Install Scout and an engine, then point Laradocs at it:

```bash
composer require laravel/scout
# configure SCOUT_DRIVER + your engine credentials per the Scout docs
php artisan laradocs:index
```

Laradocs indexes pages through Scout without needing an Eloquent model or a
database table, and maps results back onto the pre-rendered index for display.
Re-run `laradocs:index` (or `laradocs:cache`) after changing content to refresh
the engine.

## Excluding pages from the index

### Per-page opt-out

Add `search: false` to any page's front-matter to keep it out of the index while
leaving the URL fully reachable:

```markdown
---
title: Internal Notes
search: false
---
```

Hidden pages (`hidden: true`) are never indexed regardless of the `search:` field.

### Config-level exclude list

Use `search.exclude` in `config/laradocs.php` to exclude a set of pages without
touching each file. Values are [fnmatch](https://www.php.net/fnmatch) slug patterns:

```php
'search' => [
    'exclude' => [
        'internal/*',   // all pages under /docs/internal/
        'changelog',    // the exact slug "changelog"
    ],
],
```

Excluded pages are still reachable — they just won't appear in search results.

### Config-level include allow-list

When `search.include` is non-empty, **only** pages whose slug matches a pattern
are indexed. Everything else is excluded:

```php
'search' => [
    'include' => [
        'guide/*',
        'reference/*',
    ],
],
```

`search: false` front-matter is still respected inside an include pattern — a page
matching an `include` pattern is still excluded if it declares `search: false`.
`exclude` patterns take priority over `include` patterns for the same slug.

## Disabling search

Set `LARADOCS_SEARCH=false` (the `ui.search.enabled` flag) to hide the palette's
search box and disable the endpoint entirely.

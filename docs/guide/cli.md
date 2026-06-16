---
title: CLI
description: Every Artisan command shipped by Laradocs.
---

# CLI

Laradocs registers these Artisan commands. Run any of them with
`php artisan {name}`.

## `docs:check`

```bash
php artisan docs:check [--json]
```

Walks the entire docs tree and reports:

- **Broken internal links** — markdown links whose resolved slug does not match any
  loaded document (e.g. `[text](/docs/missing-page)`).
- **Orphaned pages** — documents that are unreachable: absent from the navigation tree
  (i.e. `hidden`) *and* not the target of any internal link from another page. Visible
  pages always appear in the auto-generated navigation, so the orphans surfaced here are
  hidden pages that nothing links to — dead content you can reach by neither the menu nor
  a cross-reference.
- **Redirect cycles** — chains of `redirect:` front-matter that loop back to an earlier
  slug (e.g. `a → b → a`). Only redirects whose target is a known slug are followed, so a
  dangling redirect never produces a false positive.

The command exits with a non-zero status whenever any finding is found, making it
suitable for use in CI pipelines.

Pass `--json` to receive a structured JSON report instead of formatted output:

```bash
php artisan docs:check --json
```

```json
{
  "broken_links": [
    { "source": "guide/intro", "href": "/docs/missing", "slug": "missing" }
  ],
  "orphans": [],
  "redirect_cycles": [
    { "cycle": ["old-page", "new-page", "old-page"] }
  ],
  "summary": {
    "broken_links": 1,
    "orphans": 0,
    "redirect_cycles": 1,
    "total": 2
  }
}
```

> [!NOTE]
> Only links whose path begins with the configured route prefix (default `/docs`) are
> checked. External URLs, anchor-only links, and links to other parts of your application
> are ignored.

> [!TIP]
> Add `php artisan docs:check` as a step in your CI workflow to catch broken links
> before they reach production.

### Fixture coverage

The test suite exercises the following scenarios for `docs:check`:

| Scenario | Expected outcome |
|---|---|
| Clean docs (all links resolve) | Exit 0, no findings |
| Markdown link to a non-existent slug | Exit 1, reported in `broken_links` |
| External / anchor-only links | Ignored — no false positives |
| Internal link with `#anchor` suffix | Anchor stripped before slug lookup |
| Two docs redirecting to each other | Exit 1, cycle reported |
| Redirect written as `/docs/...` (prefixed) | Resolved to a slug before cycle detection |
| Redirect pointing to a missing slug | No cycle reported (target unknown) |
| Hidden page nothing links to | Exit 1, reported as an orphan |
| Hidden page linked from another page | Not reported as an orphan |
| `--json` flag | JSON to stdout, same exit codes |

## `docs:lint`

```bash
php artisan docs:lint [--json]
```

Validates the front-matter of every document and reports four categories of problem:

- **Missing required fields** — any field listed in `laradocs.lint.required` that is absent
  or empty. Defaults to `['title']`; add any front-matter key you want to enforce
  (e.g. `description`, `author`, `updated_at`).
- **Slug collisions** — two or more documents that resolve to the same URL slug, whether
  from a path-derived slug or an explicit `slug:` front-matter override.
- **Unknown layout names** — when `laradocs.lint.layouts` is non-empty, any `layout:` value
  that is not in that allowlist. An empty list disables the check entirely.
- **Invalid `updated_at` formats** — a present `updated_at` value that cannot be parsed as a
  recognised date or datetime. Accepted formats: `YYYY-MM-DD`,
  `YYYY-MM-DD HH:MM:SS`, `YYYY-MM-DDTHH:MM:SS`, and `YYYY-MM-DDTHH:MM:SS+HH:MM`.

The command exits with a non-zero status whenever any finding is reported, making it
suitable for CI pipelines.

Pass `--json` to receive a structured report instead of formatted output:

```bash
php artisan docs:lint --json
```

```json
{
  "missing_fields": [
    { "slug": "guide/intro", "path": "guide/intro.md", "field": "title" }
  ],
  "slug_collisions": [
    { "slug": "intro", "paths": ["intro.md", "sub/intro.md"] }
  ],
  "unknown_layouts": [
    { "slug": "landing", "path": "landing.md", "layout": "ghost" }
  ],
  "invalid_dates": [
    { "slug": "guide/intro", "path": "guide/intro.md", "value": "March 2026" }
  ],
  "summary": {
    "missing_fields": 1,
    "slug_collisions": 1,
    "unknown_layouts": 1,
    "invalid_dates": 1,
    "total": 4
  }
}
```

### Configuration

The linter is configured under the `lint` key in `config/laradocs.php`:

```php
'lint' => [
    // Fields every document must declare. Use the YAML key name.
    'required' => ['title'],

    // Allowlist of valid layout names. Empty = skip the layout check.
    'layouts' => [],
],
```

To enforce additional required fields across your whole docs tree, extend the list:

```php
'required' => ['title', 'description', 'updated_at'],
```

To restrict which layouts documents may use, set an explicit allowlist:

```php
'layouts' => ['docs', 'landing', 'changelog'],
```

> [!NOTE]
> YAML 1.1 (used by the underlying parser) silently converts bare date scalars such as
> `updated_at: 2026-01-15` to Unix timestamps before Laradocs sees them. The linter
> recognises this and does not flag such values. To write a date string that bypasses
> YAML's conversion, quote it: `updated_at: '2026-01-15'`.

> [!TIP]
> Add `php artisan docs:lint` as a CI step alongside `docs:check` to enforce
> front-matter quality gates before every deployment.

### Fixture coverage

| Scenario | Expected outcome |
|---|---|
| All docs have required fields, valid dates, and known layouts | Exit 0, no findings |
| Document missing a required field (e.g. `title`) | Exit 1, reported in `missing_fields` |
| Required-field list overridden via config | Only configured fields enforced |
| Required list empty | Missing-field check disabled; exit 0 |
| Two docs resolve to the same slug | Exit 1, reported in `slug_collisions` |
| `layout:` value not in the allowlist | Exit 1, reported in `unknown_layouts` |
| `layouts` config is empty | Layout check skipped; exit 0 |
| `layout:` value matches the allowlist | Not reported |
| `updated_at: 2026-01-15` (bare YAML date) | Accepted (YAML converts to timestamp) |
| `updated_at: '2026-03-15 10:30:00'` (quoted datetime) | Accepted |
| `updated_at: March 2026` | Exit 1, reported in `invalid_dates` |
| `updated_at` absent | Not reported |
| `--json` flag | JSON to stdout, same exit codes |
| Multiple finding types at once | All reported; summary total is their sum |

## `laradocs:install`

```bash
php artisan laradocs:install [--force]
```

Publishes `config/laradocs.php`, ensures `docs.path` exists, and writes
a starter `index.md` if one isn't already there. Pass `--force` to
overwrite an existing config or starter page.

## `make:doc`

```bash
php artisan make:doc {name} \
    [--title=...] [--group=...] [--order=...] [--force]
```

Scaffolds a new markdown file inside your docs directory. `name` is the
slug-style path you want (e.g. `guide/installation` or
`reference/migration.md` — the extension is optional).

| Option | Effect |
|---|---|
| `--title` | Sets the front-matter `title:` (defaults to a humanised filename). |
| `--group` | Sets the front-matter `group:`. |
| `--order` | Sets the front-matter `order:`. |
| `--force` | Overwrite if the file already exists. |

The output is produced from a Blade stub you can publish and edit — see
[Customising stubs](/docs/customising-stubs).

## `laradocs:cache`

```bash
php artisan laradocs:cache
```

Pre-renders every visible document, warms the cache, stores the
navigation tree, generates the [sitemap](/docs/guide/sitemap), and
rebuilds the search index. Hooked into Laravel's optimizer, so
`php artisan optimize` calls it automatically.

## `laradocs:index`

```bash
php artisan laradocs:index
```

Builds the full-text [search](/docs/guide/search) index and pushes it to
the configured engine (a Scout backend, or the built-in JSON index). Run
automatically as part of `laradocs:cache`; run it on its own to refresh
just the index.

## `laradocs:clear`

```bash
php artisan laradocs:clear
```

Flushes all cached HTML, the navigation tree, the sitemap and the search
index. Hooked into `php artisan optimize:clear`.

> [!TIP]
> Editing a file invalidates only that file's cache (keyed on
> `mtime`), so during development you can leave the cache enabled and
> still see changes immediately.

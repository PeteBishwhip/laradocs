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
- **Orphaned pages** — visible documents that are not reachable via the navigation tree.
- **Redirect cycles** — chains of `redirect:` front-matter that loop back to an earlier
  slug (e.g. `a → b → a`).

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
| Redirect pointing to a missing slug | No cycle reported (target unknown) |
| Hidden document | Not reported as an orphan |
| `--json` flag | JSON to stdout, same exit codes |

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

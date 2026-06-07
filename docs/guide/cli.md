---
title: CLI
description: Every Artisan command shipped by Laradocs.
---

# CLI

Laradocs registers five Artisan commands. Run any of them with
`php artisan {name}`.

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

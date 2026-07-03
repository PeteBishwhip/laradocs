# Laradocs

[![tests](https://github.com/PeteBishwhip/laradocs/actions/workflows/tests.yml/badge.svg)](https://github.com/PeteBishwhip/laradocs/actions/workflows/tests.yml)
[![quality](https://github.com/PeteBishwhip/laradocs/actions/workflows/quality.yml/badge.svg)](https://github.com/PeteBishwhip/laradocs/actions/workflows/quality.yml)
[![Latest Version](https://img.shields.io/packagist/v/petebishwhip/laradocs.svg)](https://packagist.org/packages/petebishwhip/laradocs)
[![License](https://img.shields.io/packagist/l/petebishwhip/laradocs.svg)](LICENSE.md)

Maintain beautiful, version-controlled documentation **inside** your Laravel
codebase. Write markdown, commit it next to the code it describes, and Laradocs
serves a polished docs site at `/docs` (or wherever you like).

```bash
composer require petebishwhip/laradocs
php artisan laradocs:install
```

Then open `/docs`.

## Requirements

| | Minimum | Notes |
|---|---|---|
| PHP | 8.2 | |
| Laravel | 11.14 | 12 and 13 fully supported |
| dedoc/scramble | 0.13 | Optional — only needed for the `scramble` OpenAPI driver |

## Features

- 📁 **Multi-level file structure** — nested folders become nested navigation.
- 🔗 **Filename _or_ metadata routing** — `slug:` front-matter overrides paths.
- 📝 **Markdown → HTML** powered by CommonMark (GFM, tables, footnotes, …).
- 🏷️ **Rich per-file metadata** — `title`, `description`, `order`, `hidden`,
  `group`, `badge`, `redirect`, `tags`, and more.
- 🎨 **Polished default UI** — responsive, dark-mode, sidebar, breadcrumbs,
  on-page table of contents, prev/next — all publishable and overridable.
- ⚡ **Smart caching** — rendered HTML cached and auto-invalidated on file change.
- 🧩 **Variables & macros** — interpolate `{{ values }}` and reuse `@docs()` blocks,
  with a service-provider API to register your own.
- 🖼️ **Rich content** — callouts (`> [!NOTE]`), syntax-highlighted code with a
  copy button, lazy images with captions, and local/YouTube/Vimeo video embeds.
- 🔎 **Automatic SEO** — `<title>`, meta description, Open Graph & Twitter cards,
  canonical URLs and JSON-LD for every page, with per-page front-matter overrides.
- 🗺️ **Sitemap** — an auto-generated `sitemap.xml` at `{prefix}/sitemap.xml`,
  cached and invalidated alongside the rest of the docs cache.
- ✅ **Fully tested** — Pest + Testbench, 100% coverage gate, PHPStan & Psalm max, Pint.

## Quick start

Create a page:

```bash
php artisan make:doc guide/getting-started --title="Getting Started" --order=1
```

```markdown
---
title: Getting Started
description: Install and configure the app.
order: 1
group: Basics
---

# Getting Started

> [!TIP]
> Folders become sidebar sections; `_index.md` is a section's landing page.
```

## Configuration

Everything is configurable in `config/laradocs.php` and via environment
variables — route prefix/domain, docs path, routing strategy, theme, caching and
more. See the [Configuration docs](https://laradocs.dev/docs/configuration).

```dotenv
LARADOCS_ROUTE_PREFIX=docs
LARADOCS_THEME=auto
LARADOCS_ENABLED=true
```

## The Laradocs facade

```php
use Laradocs\Facades\Laradocs;

Laradocs::variables(fn () => ['version' => '1.0.0']);
Laradocs::share('app_name', config('app.name'));
Laradocs::macro('tweet', fn (array $args) => "<a href=\"...\">@{$args['user']}</a>");
```

## Artisan commands

| Command | Description |
|---|---|
| `laradocs:install` | Publish config and scaffold a starter page |
| `make:doc {name}` | Scaffold a new markdown page with front-matter |
| `laradocs:cache` | Pre-render and cache every page |
| `laradocs:clear` | Clear the documentation cache |
| `laradocs:openapi` | Generate an OpenAPI spec from your routes (`--driver=auto\|native\|scramble`) |

## Publishing

```bash
php artisan vendor:publish --tag=laradocs-config
php artisan vendor:publish --tag=laradocs-views
php artisan vendor:publish --tag=laradocs-assets
php artisan vendor:publish --tag=laradocs-lang
```

## Testing

```bash
composer test
```

## Local development (workbench)

The package ships an [orchestra/testbench](https://packages.tools/testbench)
**workbench** — a disposable Laravel app used to run Laradocs as a real,
browsable site while you work on the package itself, rather than through Pest
alone.

```bash
composer serve
```

This builds the workbench (`testbench workbench:build`) and boots it at
`/docs`. The generated app lives at `vendor/orchestra/testbench-core/laravel`
— it's regenerated on demand and isn't committed to git.

**By default the workbench has no docs content**, so `/docs` renders an empty
state. Point it at the real docs in this repo (so edits under `docs/` show up
immediately, thanks to Laradocs' mtime-based cache) by adding to the
workbench's `.env`:

```dotenv
# vendor/orchestra/testbench-core/laravel/.env
LARADOCS_PATH=/absolute/path/to/laradocs/docs
```

Or seed your own throwaway fixtures directly under the workbench's `docs/`
and `lang/vendor/laradocs/<locale>/` — useful for exercising a specific
feature (a locale, a version, a front-matter combination) without touching
the real docs. Any `config/laradocs.php` option can be set via the
workbench's own `.env`, exactly as in a consumer app — e.g.
`LARADOCS_LOCALE_AVAILABLE={"en":"English","fr":"Français"}` to test
localisation.

For finer control than `composer serve` gives you — e.g. driving the app with
`curl` instead of a browser — build and serve it yourself:

```bash
composer run build              # just (re)build the workbench, don't serve
cd vendor/orchestra/testbench-core/laravel
php artisan config:clear        # pick up .env changes — Laravel may have cached the old config
php artisan serve               # or: php -S 127.0.0.1:8000 -t public public/index.php
```

Two gotchas worth knowing:

- **`composer dump-autoload` wipes the workbench.** The package's
  `post-autoload-dump` hook runs `testbench package:purge-skeleton`, which
  deletes the generated app — including any `.env` changes or fixtures you
  added. Re-run `composer run build` (or `composer serve`) afterwards to
  regenerate it.
- **Config changes need `config:clear`.** After editing the workbench's
  `.env`, run `php artisan config:clear` inside it if the change doesn't
  seem to take effect.

## Documentation

The full docs live at **[laradocs.dev/docs](https://laradocs.dev/docs)** — and
are themselves built with Laradocs. Highlights:

- [Getting started](https://laradocs.dev/docs/getting-started)
- [Configuration](https://laradocs.dev/docs/configuration)
- [Routing](https://laradocs.dev/docs/guide/routing)
- [Metadata](https://laradocs.dev/docs/guide/metadata)
- [Caching](https://laradocs.dev/docs/guide/caching)
- [SEO](https://laradocs.dev/docs/guide/seo)
- [Sitemap](https://laradocs.dev/docs/guide/sitemap)
- [CLI reference](https://laradocs.dev/docs/guide/cli)
- [PHP API](https://laradocs.dev/docs/guide/api)
- [Variables](https://laradocs.dev/docs/features/variables) ·
  [Macros](https://laradocs.dev/docs/features/macros) ·
  [Rich content](https://laradocs.dev/docs/features/rich-content)
- [Customising the UI](https://laradocs.dev/docs/customising-the-ui) ·
  [Customising stubs](https://laradocs.dev/docs/customising-stubs)
- [Migration guide: 0.x → 1.0](https://laradocs.dev/docs/migration-guide)

The source for those pages lives in [`docs/`](docs); browse there or serve a
local copy with `composer serve`.

## Sponsors

Laradocs is free and open source. If it saves you time, please consider
[sponsoring its development](https://github.com/sponsors/PeteBishwhip) — it keeps
the project actively maintained.

<p align="center">
  <a href="https://github.com/sponsors/PeteBishwhip">
    <img src="./sponsors.svg" alt="Sponsors of PeteBishwhip" />
  </a>
</p>

The image above is regenerated daily by the [`Scheduler`](.github/workflows/scheduler.yml)
workflow via [sponsorkit](https://github.com/antfu/sponsorkit).

## Contributing & Security

See [CONTRIBUTING.md](CONTRIBUTING.md) and [SECURITY.md](SECURITY.md).

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).

## Star History

<a href="https://www.star-history.com/?repos=PeteBishwhip%2Flaradocs&type=timeline&legend=top-left">
 <picture>
   <source media="(prefers-color-scheme: dark)" srcset="https://api.star-history.com/chart?repos=PeteBishwhip/laradocs&type=timeline&theme=dark&legend=top-left" />
   <source media="(prefers-color-scheme: light)" srcset="https://api.star-history.com/chart?repos=PeteBishwhip/laradocs&type=timeline&legend=top-left" />
   <img alt="Star History Chart" src="https://api.star-history.com/chart?repos=PeteBishwhip/laradocs&type=timeline&legend=top-left" />
 </picture>
</a>

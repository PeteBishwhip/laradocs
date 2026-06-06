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
- ✅ **Fully tested** — Pest + Testbench, 100% coverage gate, PHPStan max, Pint.

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

## Publishing

```bash
php artisan vendor:publish --tag=laradocs-config
php artisan vendor:publish --tag=laradocs-views
php artisan vendor:publish --tag=laradocs-assets
```

## Testing

```bash
composer test
```

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

The source for those pages lives in [`docs/`](docs); browse there or serve a
local copy with `composer serve`.

## Contributing & Security

See [CONTRIBUTING.md](CONTRIBUTING.md) and [SECURITY.md](SECURITY.md).

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).

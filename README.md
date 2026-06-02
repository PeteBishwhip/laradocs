# Laradocs

[![tests](https://github.com/pete/laradocs/actions/workflows/tests.yml/badge.svg)](https://github.com/pete/laradocs/actions/workflows/tests.yml)
[![quality](https://github.com/pete/laradocs/actions/workflows/quality.yml/badge.svg)](https://github.com/pete/laradocs/actions/workflows/quality.yml)
[![Latest Version](https://img.shields.io/packagist/v/petebishwhip/laradocs.svg)](https://packagist.org/packages/petebishwhip/laradocs)
[![License](https://img.shields.io/packagist/l/petebishwhip/laradocs.svg)](LICENSE.md)

Maintain beautiful, version-controlled documentation **inside** your Laravel
codebase. Write markdown, commit it next to the code it describes, and Laradocs
serves a polished docs site at `/docs` (or wherever you like).

```bash
composer require petebishwhip/laradocs
php artisan docs:install
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
more. See the [Configuration docs](docs/configuration.md).

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
| `docs:install` | Publish config and scaffold a starter page |
| `make:doc {name}` | Scaffold a new markdown page with front-matter |
| `docs:cache` | Pre-render and cache every page |
| `docs:clear` | Clear the documentation cache |

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

This package documents itself — the [`docs/`](docs) folder is a live Laradocs
site. Browse it on disk or serve it with `composer serve`.

## Contributing & Security

See [CONTRIBUTING.md](CONTRIBUTING.md) and [SECURITY.md](SECURITY.md).

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).

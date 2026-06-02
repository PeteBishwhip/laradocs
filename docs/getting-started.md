---
title: Getting Started
description: Install Laradocs and publish your first page in minutes.
order: 2
---

# Getting Started

## Installation

Require the package with Composer:

```bash
composer require petebishwhip/laradocs
```

Run the installer to publish the config file and scaffold a starter page:

```bash
php artisan docs:install
```

That's it. Visit `/docs` in your browser and you'll see your documentation.

## Creating pages

Every markdown file under your `docs/` directory becomes a page. Use the
generator to scaffold one with front-matter already filled in:

```bash
php artisan make:doc guide/installation --title="Installation" --order=1
```

> [!TIP]
> Folders become navigation sections. A file named `_index.md` inside a folder
> becomes that section's landing page.

The front-matter and body that `make:doc` writes come from a Blade stub you
can publish and edit — see [Customising stubs](/docs/customising-stubs).

## What next?

- Configure routes, caching and the UI in [Configuration](/docs/configuration).
- Learn how URLs are generated in [Routing](/docs/guide/routing).
- Inject dynamic values with [Variables](/docs/features/variables).

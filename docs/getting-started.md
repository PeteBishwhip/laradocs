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
php artisan laradocs:install
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
can publish and edit — see [Customising stubs](/docs/customisation/stubs).

## Finding pages

The header includes a command palette — press **⌘K** (or **Ctrl K** on
Windows / Linux) anywhere on the docs site to open it, then type to
fuzzy-filter every page. Use the top tabs to switch between major
sections, the left sidebar to navigate within a section, and the
right-hand TOC to jump between headings on the current page.

## What next?

- Configure routes, caching and the UI in
  [Configuration](/docs/configuration).
- Learn how URLs are generated in [Routing](/docs/navigation/routing).
- Tune titles, social cards and structured data in [SEO](/docs/seo).
- Discover every Artisan command in [CLI](/docs/cli).
- Inject dynamic values with [Variables](/docs/content/variables).
- Restyle the bundled views in
  [Customising the UI](/docs/customisation/ui).

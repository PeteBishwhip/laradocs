---
title: Laradocs
description: Maintain beautiful documentation inside your Laravel codebase.
order: 1
---

# Laradocs

Laradocs turns a folder of markdown files into a polished, searchable
documentation site served directly from your Laravel application. Write
markdown, commit it alongside your code, and your docs stay in lock-step with
the features they describe.

> [!NOTE]
> This very site is built with Laradocs — the `docs/` folder you are reading
> is the package documenting itself.

## Why Laradocs?

- **Markdown-first.** Author content in plain `.md` files with YAML front-matter.
- **Zero-config routing.** Drop files in `docs/`, get routes at `/docs`.
- **Multi-level structure.** Nested folders become nested navigation, with
  sectioned tabs across the top for the major groups.
- **Built-in command palette.** Press `⌘K` (or click the search box) to jump
  between pages with fuzzy matching.
- **Batteries included.** Callouts, code highlighting, footnotes, attribute
  lists, video embeds, variables, macros, dark mode, a scroll-spying TOC,
  reading-progress bar, prev/next pager, edit links, and three layout
  presets — all out of the box.
- **SEO without the busywork.** Titles, meta descriptions, Open Graph and
  Twitter cards, canonical URLs and JSON-LD are generated for every page —
  with per-page front-matter overrides. See [SEO](/docs/guide/seo).
- **Inter + Laravel red.** Modern type, clean hairline borders, the brand
  accent built in. Override any token from config.

@docs('button', text: 'Get started', href: '/docs/getting-started')

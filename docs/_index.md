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
- **Multi-level structure.** Nested folders become nested navigation.
- **Batteries included.** Callouts, code highlighting, video embeds, variables,
  macros, dark mode and a responsive UI — all out of the box.

@docs('button', text: 'Get started', href: '/docs/getting-started')

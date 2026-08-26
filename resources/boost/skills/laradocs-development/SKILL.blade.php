---
name: laradocs-development
description: "Use this skill when authoring documentation pages or configuring Laradocs in a Laravel application. Trigger when creating or editing markdown under the docs path, writing or fixing YAML front-matter (title, slug, order, group, tags, hidden, updated_at, search, search_rank), running make:doc / docs:lint / docs:check / laradocs:* commands, writing rich content (callouts, fenced code, image captions, video embeds, Mermaid diagrams, KaTeX math), registering variables or macros, using the Laradocs facade, publishing config/views/lang/assets, or configuring routing, search, SEO, feeds, tags, versions or locales. Use it whenever the user mentions Laradocs, a docs site, a docs page, or documentation front-matter."
license: MIT
metadata:
  author: petebishwhip
---
# Laradocs Development

## Documentation

Use `search-docs` for detailed Laravel patterns. For Laradocs itself, read
`references/rich-content.md` for the full markdown syntax (callouts, code
blocks, images, video, Mermaid, KaTeX) and `references/extending.md` for
variables, macros, the facade API, publishing and the config surface.

## File structure & conventions

- Docs live under `laradocs.docs.path` (default `base_path('docs')`) and use the
  `.md` or `.markdown` extension.
- Nested folders become nested navigation, and directory depth maps to URL
  depth: `docs/guide/routing.md` is served at `/docs/guide/routing`.
- `_index.md` is a section landing page: `docs/guide/_index.md` → `/docs/guide`,
  and `docs/_index.md` → `/docs`. Configurable via `laradocs.docs.index`.
- Use kebab-case filenames — the filename becomes the URL slug. Dotfiles,
  `_drafts` and `README.md` are ignored (`laradocs.docs.ignored_patterns`).
- `laradocs.routing.strategy` decides where a slug comes from: `filename`,
  `metadata` (front-matter `slug:`, falling back to the filename), or `both`
  (default — `slug:` wins when present).

## Front-matter

Every document opens with a YAML block delimited by `---`. **Keys are
snake_case** (`updated_at`, `search_rank`), never camelCase.

| Key | Type | Default | Meaning |
|---|---|---|---|
| `title` | string | — | Page title (heading, `<title>`, sidebar). Required by the default linter. |
| `description` | string | — | Summary for `<meta>` description / SEO / social cards. |
| `slug` | string | — | Override the URL slug (honoured when `routing.strategy` is `metadata` or `both`). |
| `order` | int | `999` | Sort order in the sidebar; lower appears first. |
| `hidden` | bool | `false` | Hide from sidebar, listings, sitemap, feed and search. |
| `group` | string | — | Sidebar group/bucket the page sits under. |
| `badge` | string | — | Small label shown next to the title in the sidebar. |
| `icon` | string | — | Icon name (consumed by your views/macros). |
| `tags` | array | `[]` | Free-form tags; power the auto-generated tag index pages. |
| `updated_at` | string | — | Last-updated date. Accepted: `Y-m-d`, `Y-m-d H:i:s`, ISO 8601. |
| `author` | string | — | Author attribution (article meta + schema). |
| `layout` | string | — | Override the Blade layout. Validated against `lint.layouts` when that list is non-empty. |
| `image` | string | — | Social/OG image; wins over `seo.image` and any generated card. |
| `redirect` | string | — | Permanent redirect to another URL. |
| `search` | bool | `true` | Set `false` to exclude the page from the search index. |
| `search_rank` | float | `1.0` | Ranking multiplier (>1 boosts, <1 demotes) in the built-in JSON search. |

Any key without a dedicated property above is captured under `metadata.extra`
(e.g. a `seo:` block), so custom keys are preserved rather than dropped.

@verbatim
<code-snippet name="Document front-matter" lang="yaml">
---
title: Routing
description: How Laradocs maps files to URLs.
group: Guides
order: 3
tags: [routing, urls]
updated_at: 2026-01-15
author: Jane Doe
search_rank: 1.5
---

# Routing

Your markdown content starts here.
</code-snippet>
@endverbatim

`php artisan docs:lint` enforces the fields in `laradocs.lint.required`
(default `['title']`).

## Artisan commands

- `php artisan laradocs:install {--force}` — publish the config and scaffold a
  starter docs folder.
- `php artisan make:doc {name} {--title=} {--group=} {--order=} {--force}` —
  scaffold a page with correct front-matter. **Prefer this over writing the file
  by hand.** `name` is the doc path, e.g. `guide/getting-started`.
- `php artisan docs:lint` — validate front-matter across every page.
- `php artisan docs:check` — check for broken internal links and other problems.
- `php artisan laradocs:cache` — pre-render pages and the navigation tree.
- `php artisan laradocs:clear` — clear cached HTML, navigation and search index.
  **Run this after changing config, macros or variables.**
- `php artisan laradocs:index` — build the search index.

Run `php artisan list laradocs` to see the full set, and `--help` on any command
for its exact options rather than guessing.

## Best practices

- Always include a `title`; prefer `make:doc` to create pages.
- Use `_index.md` for section landing pages; nest folders to nest navigation.
- Use `> [!TYPE]` callouts and fenced code blocks rather than raw HTML.
- Register dynamic variables and macros via the facade in a service provider —
  never put closures in config files, they break `config:cache`.
- Run `docs:lint` and `docs:check` before commit or deploy.
- Clear the cache after editing config, macros or variables.

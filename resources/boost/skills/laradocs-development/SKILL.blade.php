---
name: laradocs-development
description: "Use this skill when authoring documentation pages or configuring Laradocs in a Laravel application. Trigger when creating or editing markdown under the docs path, writing or fixing YAML front-matter (title, slug, order, group, tags, hidden, updated_at, search, search_rank), running make:doc / docs:lint / docs:check / laradocs:* commands, writing rich content (callouts, fenced code, image captions, video embeds, Mermaid diagrams, KaTeX math, tabs), registering variables or macros, using the Laradocs facade, publishing config/views/lang/assets, or configuring routing, search, SEO, feeds, tags, versions or locales. Use it whenever the user mentions Laradocs, a docs site, a docs page, or documentation front-matter."
license: MIT
metadata:
  author: petebishwhip
---
# Laradocs Development

## Read the shipped documentation

Laradocs ships its own documentation inside the installed package, at
`vendor/petebishwhip/laradocs/docs`. **Read the relevant page there before
answering from memory or guessing at an option** — it is written for the exact
version installed, which the Boost `search-docs` tool does not cover.

Start here for the task at hand:

| Task | Page |
|---|---|
| Front-matter fields | `docs/navigation/metadata.md` |
| Slugs, URLs, `_index.md` | `docs/navigation/routing.md` |
| Sidebar groups and tabs | `docs/navigation/grouping.md` |
| Tags and tag index pages | `docs/navigation/tags.md` |
| Callouts, code blocks, tabs, images, video, Mermaid, KaTeX | `docs/content/rich-content.md` |
| Variables (`@{{ key }}`) | `docs/content/variables.md` |
| Macros and `@verbatim<x-name>@endverbatim` components | `docs/content/macros.md`, `docs/content/components.md` |
| Icons | `docs/content/icons.md` |
| Media beside the markdown | `docs/content/media.md` |
| OpenAPI reference pages | `docs/content/openapi.md` |
| Artisan commands | `docs/cli.md` |
| Every config key | `docs/configuration.md` |
| Theme, brand, layout overrides | `docs/customisation/ui.md`, `docs/customisation/stubs.md` |
| SEO, sitemap, robots, llms.txt | `docs/seo/` |
| Search engines and ranking | `docs/navigation/search.md` |
| Caching, versioning, locales, Octane, visibility | `docs/advanced/` |
| The facade and HTTP API | `docs/http-api/php.md` |
| Deploying | `docs/deployment/` |
| Upgrading a major version | `docs/migration-guide.md` |

`docs/_index.md` lists everything if the table above does not cover it. Pages
under `docs/guide/` and `docs/features/` are redirect stubs kept for old links —
follow their `redirect:` front-matter to the canonical page rather than reading
them.

## Authoring a page

- Create pages with `php artisan make:doc {name}` — it writes correct
  front-matter. `name` is the doc path, e.g. `guide/getting-started`.
- Pages live under `laradocs.docs.path` (default `base_path('docs')`) and use
  `.md` or `.markdown`.
- Nested folders become nested navigation, and directory depth maps to URL
  depth: `docs/guide/routing.md` is served at `/docs/guide/routing`.
- `_index.md` is a section landing page: `docs/guide/_index.md` → `/docs/guide`.
- Use kebab-case filenames; the filename becomes the slug. Dotfiles, `_drafts`
  and `README.md` are ignored.
- Front-matter keys are snake_case (`updated_at`, `search_rank`), and `title` is
  required by the default linter. Unknown keys are preserved, reachable via
  `$document->metadata->get('your_key')`.

## Working on the package's configuration

- Every option lives in `config/laradocs.php`; publish it with
  `php artisan vendor:publish --tag=laradocs-config`. Other tags:
  `laradocs-views`, `laradocs-lang`, `laradocs-assets`, `laradocs-stubs`,
  `laradocs-all`.
- **No closures in config files** — they break `config:cache`. Register dynamic
  variables and macros through the `Laradocs` facade in a service provider's
  `boot()` instead.
- A published view takes precedence over the package's own, so `composer update`
  will not bring upstream changes to it. Diff published views against
  `vendor/petebishwhip/laradocs/resources/views/` after upgrading.

## Commands

Run `php artisan list laradocs` and `--help` on a command for its exact options
rather than guessing. The ones you will reach for:

- `php artisan make:doc {name}` — scaffold a page.
- `php artisan docs:lint` — validate front-matter across every page.
- `php artisan docs:check` — find broken internal links.
- `php artisan laradocs:clear` — clear cached HTML, navigation and search index.
  **Run this after changing config, macros or variables.**
- `php artisan laradocs:cache` / `laradocs:index` — warm the caches and build
  the search index.

Run `docs:lint` and `docs:check` before committing or deploying.

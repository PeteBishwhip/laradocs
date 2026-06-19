# Laradocs

Laradocs (`petebishwhip/laradocs`) is a Laravel package that turns a folder of
markdown files into a polished, searchable documentation site served from the
host application. Docs are plain `.md`/`.markdown` files (with YAML front-matter)
that live under `docs/` and are served at `/docs`. Install with
`composer require petebishwhip/laradocs` then `php artisan laradocs:install` to
publish the config and scaffold a starter page. These guidelines tell you how to
author docs and drive the package correctly — write valid front-matter, use the
right Artisan commands, the facade API, the rich-content syntax, and config.

## Documentation file structure & conventions

- Docs files live under the configured docs path (`laradocs.docs.path`, default
  `base_path('docs')`) and use the `.md` or `.markdown` extension.
- Nested folders become nested navigation sections, and directory depth maps to
  URL depth: `docs/guide/routing.md` is served at `/docs/guide/routing`.
- `_index.md` is a section landing page: `docs/guide/_index.md` →
  `/docs/guide`, and the root `docs/_index.md` → `/docs` (the docs home). The
  index filename is configurable via `laradocs.docs.index` (default `_index`).
- Use kebab-case filenames (the filename becomes the URL slug). Dotfiles, the
  `_drafts` directory, and `README.md` are ignored by default
  (`laradocs.docs.ignored_patterns`).
- Slug routing is controlled by `laradocs.routing.strategy`:
  `filename` (slug from the file path), `metadata` (slug from front-matter
  `slug:`, falling back to the filename), or `both` (default — front-matter
  `slug:` wins when present, otherwise the filename is used).

## Front-matter metadata

Every document opens with a YAML front-matter block delimited by `---`. **Use
snake_case YAML keys** (`updated_at`, `search_rank`) — not camelCase. Supported
fields:

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
| `updated_at` | string | — | Last-updated date. Accepted formats: `Y-m-d`, `Y-m-d H:i:s`, ISO 8601. |
| `author` | string | — | Author attribution (article meta + schema). |
| `layout` | string | — | Override the Blade layout. Validated against `lint.layouts` when that list is non-empty. |
| `image` | string | — | Social/OG image; wins over `seo.image` and any generated card. |
| `redirect` | string | — | Permanent redirect to another URL. |
| `search` | bool | `true` | Set `false` to exclude the page from the search index. |
| `search_rank` | float | `1.0` | Ranking multiplier (>1 boosts, <1 demotes) in the built-in JSON search. |

Any key without a dedicated property above is captured under `metadata.extra`
(e.g. a `seo:` block), so custom keys are preserved rather than dropped.

A complete front-matter block:

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

`php artisan docs:lint` enforces the fields listed in `laradocs.lint.required`
(default `['title']`). Keep the required fields present on every page.

## Artisan commands

Exact signatures (run from the host application):

- `php artisan laradocs:install {--force}` — publish the config and scaffold a
  starter docs folder. `--force` overwrites existing files.
- `php artisan make:doc {name} {--title=} {--group=} {--order=} {--force}` —
  scaffold a new markdown page with correct front-matter. **This is the
  preferred way to create a doc page.** `name` is the doc path, e.g.
  `guide/getting-started`.
- `php artisan docs:check {--json}` — validate internal links, detect redirect
  cycles, and surface orphaned pages.
- `php artisan docs:lint {--json}` — validate front-matter: required fields,
  slug collisions, layout names, and date formats.
- `php artisan laradocs:cache` — pre-render and cache every page plus the
  navigation tree.
- `php artisan laradocs:clear` — clear all cached HTML, navigation data and the
  search index.
- `php artisan laradocs:index` — build the full-text search index and push it to
  the configured search engine.

Hosted-platform commands (for the optional laradocs.dev hosting):

- `php artisan laradocs:login {--url=}` — authenticate the CLI with the platform.
- `php artisan laradocs:deploy {--site=} {--ref=} {--tag=} {--sha=} {--branch=} {--git}`
  — deploy docs to a hosted site.
- `php artisan laradocs:clone-project {--site=} {--force}` — pull a hosted site's
  files into the local docs directory.
- `php artisan laradocs:config {key?} {value?} {--site=} {--sync}` — read, update
  or sync a hosted site's configuration.

Run `php artisan docs:lint` and `php artisan docs:check` before committing or
deploying documentation changes.

## Rich content / markdown syntax

All of the following are enabled by default (`laradocs.parser.extensions`).

**Callouts** — GitHub-style alert blockquotes. Types: `NOTE`, `TIP`,
`IMPORTANT`, `WARNING`, `DANGER`, `CAUTION`.

@verbatim
<code-snippet name="Callout" lang="markdown">
> [!NOTE]
> Callouts render as styled boxes. The type is case-insensitive.

> [!WARNING]
> Back up your database before running this migration.
</code-snippet>
@endverbatim

**Code blocks** — fenced with a language get a copy button and a language label.

@verbatim
<code-snippet name="Fenced code" lang="markdown">
```php
echo 'Hello from a highlighted, copyable block';
```
</code-snippet>
@endverbatim

**Images with captions** — the markdown `title` becomes a caption; images are
lazy-loaded and zoomable.

@verbatim
<code-snippet name="Image with caption" lang="markdown">
![Architecture diagram](/img/architecture.png "How requests flow through Laradocs")
</code-snippet>
@endverbatim

**Video** — local `.mp4`/`.webm`/`.ogg`/`.mov` files embed via image syntax;
YouTube (`youtu.be/…`, `youtube.com/watch?v=…`) and Vimeo (`vimeo.com/…`) links
become embedded iframes.

@verbatim
<code-snippet name="Video embeds" lang="markdown">
![Demo](/media/demo.mp4)

[Watch the intro](https://youtu.be/dQw4w9WgXcQ)
</code-snippet>
@endverbatim

**Mermaid diagrams** — a ` ```mermaid ` fenced block renders to SVG.

@verbatim
<code-snippet name="Mermaid" lang="markdown">
```mermaid
graph TD
  A[Markdown] --> B[Laradocs] --> C[/docs]
```
</code-snippet>
@endverbatim

**KaTeX math** — inline `$…$` and block `$$ … $$`.

@verbatim
<code-snippet name="KaTeX" lang="markdown">
Inline math like $E = mc^2$ renders inline.

$$
\int_0^\infty e^{-x}\,dx = 1
$$
</code-snippet>
@endverbatim

Also on by default: **GFM** (tables, task lists, strikethrough), **footnotes**
(`[^1]`), **attribute lists** (`{.class #id}`), and automatic heading anchors.

## Variables & macros

**Variables** interpolate into content with `@{{ key }}` or `@{{ nested.key }}`
(ignored inside code spans and fenced blocks). Register static values in
`config('laradocs.variables')`, or dynamic values via the facade.

@verbatim
<code-snippet name="Variable usage in markdown" lang="markdown">
The current release is {{ app_version }}. Support email: {{ support.email }}.
</code-snippet>
@endverbatim

**Macros** are reusable named blocks. Invoke them with `@verbatim@docs('name', ...)@endverbatim` or
the equivalent Blade-component syntax `@verbatim<x-name attr="value">slot</x-name>@endverbatim` — the
two round-trip. Arguments may be positional or named; bare scalars are coerced
(`true`/`false` → bool, integers stay ints, otherwise string). Built-in macros:
`alert`, `badge`, `button`, `callout`, `embed`.

@verbatim
<code-snippet name="Macro usage in markdown" lang="markdown">
@docs('alert', type: 'warning', body: 'Back up your database first.')

@docs('button', text: 'Get started', href: '/docs/getting-started')

<x-callout type="tip" title="Pro tip">
Every macro is also callable as a component.
</x-callout>
</code-snippet>
@endverbatim

Register custom macros in `config('laradocs.macros')` (mapping a name to a Blade
view name) or via the facade (a closure or a view name).

**Facade API** (`use Laradocs\Facades\Laradocs;`). Register configuration in a
service provider's `boot()` method:

@verbatim
<code-snippet name="Facade registration in a service provider" lang="php">
use Laradocs\Facades\Laradocs;

public function boot(): void
{
    Laradocs::variables(fn () => [
        'app_version' => config('app.version'),
        'support' => ['email' => 'help@example.com'],
    ]);

    Laradocs::share('build', 'edge');

    Laradocs::macro('youtube', fn (string $id) => view('macros.youtube', ['id' => $id]));

    Laradocs::rateLimit(120);                                  // API rpm per IP (or false to disable)
    Laradocs::cookiesEnabled(fn () => Cookie::get('consent') === 'true');
}
</code-snippet>
@endverbatim

Configuration methods: `variables(array|Closure)`, `share(key, value)`,
`macro(name, Closure|string)`, `rateLimit(Closure|int|false)`,
`cookiesEnabled(?Closure)`. Read API: `all()`, `tree()`, `find(slug)`, `home()`,
`tags()`, `tag(slug)`, `render($document)`, `searchIndex()`, `sitemap()`,
`feed($format, $limit, $feedUrl, $siteTitle)`, `variableValues()`.

> [!IMPORTANT]
> Static `config('laradocs.variables')` and `config('laradocs.macros')` must be
> cache-safe — **no closures in config files** (they break `config:cache`). Use
> the facade in a service provider for any dynamic/runtime values or closures.

## Configuration & publishing

All configuration lives in `config/laradocs.php`. Common env vars include
`LARADOCS_ENABLED` (master switch), `LARADOCS_ROUTE_PREFIX` (default `docs`),
`LARADOCS_THEME` (`auto`/`light`/`dark`), and `LARADOCS_SITE` (hosted deploys).

Publish assets with `vendor:publish` tags:

@verbatim
<code-snippet name="Publishing tags" lang="bash">
php artisan vendor:publish --tag=laradocs-config   # config/laradocs.php
php artisan vendor:publish --tag=laradocs-views    # resources/views/vendor/laradocs/
php artisan vendor:publish --tag=laradocs-lang     # translation files
php artisan vendor:publish --tag=laradocs-assets   # compiled CSS/JS
php artisan vendor:publish --tag=laradocs-stubs    # make:doc page stub
php artisan vendor:publish --tag=laradocs-all      # everything above
</code-snippet>
@endverbatim

Config areas you may need to touch: `route` (prefix, domain, middleware),
`docs` (path, extensions, ignored patterns), `routing` (slug strategy), `parser`
(enabled extensions, highlighter, TOC), `ui` (theme, preset, accent, brand),
`seo`, `search`, `cache`, `tags`, `versions` (multi-version docs), and `locale`.

## SEO, search & feeds

- **SEO** — every page is served with a `<title>`, meta description, Open Graph
  and Twitter/X cards, canonical URL and JSON-LD. Values are derived from each
  page's content and front-matter; override per page via the `title`,
  `description`, `image` and `author` front-matter keys (or a `seo:` block).
  Dynamically generated 1200×630 OG cards are produced when a page declares no
  image.
- **Sitemap** — an auto-generated `sitemap.xml` lists every visible,
  non-redirected page, served at `{prefix}/sitemap.xml`.
- **Feed** — an RSS 2.0 or Atom 1.0 feed of the most-recently-updated pages is
  served at `{prefix}/feed.xml` (`laradocs.feed`).
- **Search** — full-text search powers the ⌘K palette. Driver is `auto`
  (Laravel Scout when available, else the built-in JSON index), `scout`, or
  `json`. Exclude a page with `search: false`; hidden pages are never indexed.

## Best-practices checklist

- Always include a `title` in front-matter (required by the default linter).
- Prefer `php artisan make:doc` to create new pages — it produces correct
  front-matter for you.
- Use `_index.md` for section landing pages; nest folders to nest navigation.
- Use snake_case front-matter keys (`updated_at`, `search_rank`).
- Run `php artisan docs:lint` and `php artisan docs:check` before commit/deploy.
- Clear the cache (`php artisan laradocs:clear`) after editing config, macros,
  or variables so changes are picked up.
- Use `> [!TYPE]` callouts and fenced code blocks rather than raw HTML.
- Register dynamic variables/macros via the `Laradocs` facade in a service
  provider — never put closures in config files.
- When you need current Laravel framework docs, use the Boost `search-docs` MCP
  tool for version-accurate answers.

# Changelog

All notable changes to `laradocs` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- MCP server endpoint (`POST /docs/_laradocs/mcp`) over Streamable-HTTP transport.
  Opt-in via `LARADOCS_MCP=true`. Exposes three tools: `search_docs`, `list_pages`, `fetch_page`.
  Connect with: `{"mcpServers":{"laradocs":{"type":"http","url":"https://example.com/docs/_laradocs/mcp"}}}`.
- MCP authentication support via any Laravel auth guard (`LARADOCS_MCP_AUTH_GUARD`).
  Open by default; set to a guard name (e.g. `api`) to require a Bearer token.
  Full Laravel Passport and Sanctum setup guide in `docs/guide/mcp.md`.

## [0.5.0] - 2026-06-17

### Added
- Localised page content and an in-header language switcher. A page can be
  translated by filename suffix (`docs/guide.fr.md`) or locale directory
  (`docs/fr/guide.md`); both resolve to the same slug so URLs stay stable
  across languages, and a missing translation falls back to the default-locale
  file so a partially translated site never 404s. First-time visitors are
  matched against their `Accept-Language` header (`detect_browser`), an
  explicit choice can be persisted in a `laradocs_locale` cookie (`cookie`,
  off by default for consent reasons), and `available` locales can be set via
  `LARADOCS_LOCALE_AVAILABLE`. See the "Localisation" guide.
- Multi-version documentation support. Serve several versions from sibling
  sub-directories of the docs path (`docs/v1/`, `docs/v2/`) under
  `/docs/{version}/{slug}`, with a version switcher in the header and
  per-version cache namespacing. Versions auto-detect from the directory tree
  (drop a `_version.json` with `{"label": "…"}` for a custom name) or can be
  listed explicitly. Configurable via the `versions` block (`enabled`,
  `default`, `available`, `selector`); opt in with `LARADOCS_VERSIONS=true`.
  See the multi-version migration guide.
- `docs:lint` Artisan command validating front-matter: flags documents missing
  required fields and, when an allowlist is set, unrecognised `layout` values.
  Configurable via the `lint` block (`required`, `layouts`).
- `docs:check` Artisan command for content integrity: validates internal links,
  detects orphaned pages, and reports redirect cycles.
- Updated translations via Crowdin.

## [0.4.0] - 2026-06-15

### Added
- Blade-component-style tags in markdown: authors can drop `<x-name attr="value">…</x-name>`
  (or self-closing `<x-name />`) straight into a page. Components resolve through the
  existing macro engine — `<x-name>` renders the macro registered under `name`, so the
  two syntaxes round-trip — and the macro registry doubles as the whitelist: only
  registered names render and attribute values are never evaluated as PHP, so there is no
  arbitrary Blade execution. Inner content is passed as a `slot` argument. A `callout`
  component ships built-in. Escape a literal tag with a backslash (`\<x-callout>`), an
  inline code span, or a fenced block. Toggle via `parser.extensions.components`. See the
  new "Components" feature guide.
- Auto-generated tag index pages. A global index at `{prefix}/tags` lists every
  tag declared in front-matter `tags:`, and `{prefix}/tag/{slug}` lists the pages
  carrying a single tag. Tags are matched by slug (case- and spacing-insensitive),
  listings respect `hidden`, and a real document occupying either slug always
  takes precedence so the routes never shadow authored pages. A page's tags now
  render as links at the foot of the page. Configurable via the `tags` block
  (`enabled`, `index`, `prefix`); the listing views are publishable Blade.

## [0.3.0] - 2026-06-11

### Added
- `sitemap.xml` served at `{prefix}/sitemap.xml`, generated from the document
  tree. Hidden and redirecting pages are skipped; `lastmod` is derived from
  `updated_at` front-matter (falling back to the file mtime) and `priority`
  falls off with depth or can be set per page via a numeric `priority`.
- Default `robots.txt` served at `{prefix}/robots.txt` with a `Sitemap:` pointer
  at the package's `sitemap.xml`. Custom `User-agent` rule blocks can be defined
  in `laradocs.robots.rules`, and `LARADOCS_ENABLED=false` collapses the body
  to a `Disallow: /` directive.
- RSS 2.0 / Atom 1.0 feed served at `{prefix}/feed.xml`, listing the most
  recently updated visible pages. Configurable via the `feed` block
  (`format`, `limit`).
- Per-page Open Graph and X/Twitter social meta tags, with front-matter
  overrides and an explicit `twitter:card` type (`x_card`).
- `route.register` config flag (`LARADOCS_ROUTE_REGISTER`) to opt out of the
  package registering its own routes, so a consumer app can wire the render
  action into a route it owns (e.g. behind tenant-resolving middleware). The
  `laradocs:cache`/`laradocs:clear` `optimize` hooks are skipped when route
  registration is off, since they rely on the package's named routes.

### Changed
- `docs.path` and `cache.key_prefix` are now resolved at request time rather
  than baked into the container at boot, so consumer apps can retarget them
  per request without rebuilding the container.
- **BREAKING:** renamed the SEO Twitter config to X. `laradocs.seo.twitter`
  becomes `laradocs.seo.x`, and the `LARADOCS_SEO_TWITTER` env var becomes
  `LARADOCS_SEO_X`. A new `laradocs.seo.x_card` (`LARADOCS_SEO_X_CARD`,
  default `summary_large_image`) sets the card type. Update any published
  config and `.env` entries when upgrading; the old keys are no longer read.

## [0.2.0] - 2026-06-06

### Added
- Mermaid diagram support: ` ```mermaid ` fenced blocks render as SVG via
  mermaid.js, imported lazily and only on pages that contain a diagram. Diagrams
  honour the active dark-mode tokens and re-render on theme change, and fall back
  to a styled code block when JavaScript is disabled. Toggle with
  `parser.extensions.mermaid`; self-host via `parser.mermaid.src`.
- Pre-rendered full-text search powering the ⌘K command palette, matching page
  bodies as well as titles.
- `laradocs:index` Artisan command to build the search index and push it to the
  configured engine; `laradocs:cache` now builds it too, and `laradocs:clear`
  flushes it.
- JSON search endpoint at `{prefix}/_laradocs/search` served by the active engine.
- Laravel Scout support (Meilisearch / Typesense / Algolia) when `laravel/scout`
  is installed, with automatic fallback to a dependency-free JSON index.
- `search` config block (`driver`, `index`, `limit`, `min_chars`, `max_chars`)
  and a per-page `search: false` front-matter flag to exclude individual pages.
- JSON API endpoints for the document tree and search, with configurable rate
  limiting.
- Automatic SEO meta tags with per-page front-matter overrides.
- Configurable global banner.
- Collapsible sidebar sections that auto-expand to the active page.
- Alphabetical navigation ordering by default; explicit `order` always wins.
- Contributor Covenant Code of Conduct.
- Grouping documentation.

### Fixed
- 500 error when a search query matched the docs landing page.
- Sidebar scrolling behind the header when a footer is present.
- Deploy-time commands (`laradocs:cache`, `laradocs:index`) are now resilient to
  search backend outages instead of failing the deploy.
- Invalid YAML front-matter in the bundled `grouping.md`.

### Security
- Added explicit `permissions` blocks to GitHub Actions workflows.

## [0.1.4] - 2026-06-04

### Added
- Fathom and Google Analytics support.

## [0.1.3] - 2026-06-04

### Changed
- **BREAKING:** renamed the `docs:` Artisan commands to `laradocs:`
  (`docs:cache` → `laradocs:cache`, `docs:clear` → `laradocs:clear`, etc.).
- Pointed the README at the laradocs.dev documentation site.

## [0.1.2] - 2026-06-04

### Added
- Mobile responsiveness and smarter edit-link placeholders.

## [0.1.1] - 2026-06-03

### Fixed
- Strip the permalink anchor before comparing a body `h1` to the page title.

## [0.1.0] - 2026-06-03

### Added
- Filesystem document loader with recursive scanning and ignore patterns.
- CommonMark-based markdown rendering (GFM, attributes, footnotes).
- Typed front-matter metadata: `title`, `description`, `slug`, `order`,
  `hidden`, `group`, `badge`, `icon`, `tags`, `updated_at`, `author`, `layout`,
  `image`, `redirect`.
- Multi-level navigation tree with `_index.md` section landing pages.
- Configurable routing (filename / metadata / both), prefix, domain, middleware.
- Polished, responsive default UI with dark mode, sidebar, breadcrumbs,
  on-page table of contents and prev/next navigation.
- Variables (`{{ key }}`) and macros (`@docs(...)`) with a service-provider API.
- Rich content: callouts, syntax-highlighted code with copy buttons, lazy
  images with captions, and local/YouTube/Vimeo video embeds.
- Cache layer with mtime-based invalidation and `optimize` integration.
- Artisan commands: `laradocs:install`, `make:doc`, `laradocs:cache`, `laradocs:clear`.
- Publishable config, views and assets; `php artisan about` integration.

[Unreleased]: https://github.com/petebishwhip/laradocs/compare/v0.4.0...HEAD
[0.4.0]: https://github.com/petebishwhip/laradocs/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/petebishwhip/laradocs/compare/v0.2.0.1...v0.3.0
[0.2.0]: https://github.com/petebishwhip/laradocs/compare/v0.1.4...v0.2.0
[0.1.4]: https://github.com/petebishwhip/laradocs/compare/v0.1.3...v0.1.4
[0.1.3]: https://github.com/petebishwhip/laradocs/compare/v0.1.2...v0.1.3
[0.1.2]: https://github.com/petebishwhip/laradocs/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/petebishwhip/laradocs/compare/0.1.0...v0.1.1
[0.1.0]: https://github.com/petebishwhip/laradocs/releases/tag/0.1.0

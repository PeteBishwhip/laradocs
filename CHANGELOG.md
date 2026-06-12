# Changelog

All notable changes to `laradocs` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

## [1.0.0] - 2026-06-02

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

[Unreleased]: https://github.com/pete/laradocs/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/pete/laradocs/releases/tag/v1.0.0

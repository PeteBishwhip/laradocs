---
title: Customising the UI
description: Branding, theming, presets, and overriding views.
order: 6
group: Advanced
---

# Customising the UI

The bundled UI is Inter-typeset, ships with a Laravel-red accent
(`#FF2D20`), and exposes every visual decision through CSS custom
properties so you can retune the look from config — no fork required.

## Layout anatomy

A typical page is composed of:

- **Header** — brand mark, centred `⌘K` search trigger, optional header
  nav links, theme toggle.
- **Sectioned tabs** — one tab per top-level sidebar group; sticks below
  the header as you scroll.
- **Sidebar** — collapsible nav grouped by metadata `group:`. Independent
  scroll container; doesn't move with the page.
- **Content** — prose column with breadcrumbs, page header, body, edit
  link, and prev/next pager.
- **Table of contents** — scroll-spying right column highlighting the
  heading you're currently reading. Hidden under 1180px.
- **Footer** — configurable text and link list (toggle with
  `LARADOCS_FOOTER`).

## Branding

```php
// config/laradocs.php
'ui' => [
    'brand' => [
        'title'   => 'Acme Docs',
        'tagline' => 'v2.4',
        'logo'    => '/img/logo.svg',
        'favicon' => '/favicon.ico',
    ],
],
```

| Field | What it does |
|---|---|
| `title` | The brand text in the header, also the default `<title>`. |
| `tagline` | A small monospace chip next to the title (e.g. a version). |
| `logo` | Path to an image; replaces the red brand mark when set. |
| `favicon` | Path to a favicon; emitted as `<link rel="icon">`. |

## Theming

```dotenv
LARADOCS_THEME=auto   # auto | light | dark
```

The theme toggle in the header cycles between **auto**, **light**, and
**dark**. The choice is persisted in `localStorage` and restored on next
visit. `auto` follows `prefers-color-scheme`.

## Accent and fonts

```dotenv
LARADOCS_ACCENT=#FF2D20
LARADOCS_FONT_SANS="Geist, system-ui, sans-serif"
LARADOCS_FONT_MONO="JetBrains Mono, monospace"
```

The accent drives links, the active TOC border, callout headers, the
brand mark, and the active tab underline. The defaults pull Inter and
JetBrains Mono from Google Fonts — set `LARADOCS_FONT_SANS` /
`LARADOCS_FONT_MONO` to override and (optionally) disable webfonts:

```php
'ui' => [
    'webfonts' => false, // stop loading from Google Fonts
],
```

The CSS tokens you can override via `--dc-*` custom properties include
`--dc-bg`, `--dc-fg`, `--dc-muted`, `--dc-rule`, `--dc-accent`,
`--dc-code-bg`, `--dc-radius`, and more (see `resources/dist/laradocs.css`).

## Presets

```dotenv
LARADOCS_UI_PRESET=classic   # classic | minimal | wide
```

| Preset | Sidebar | TOC | Content width | Best for |
|---|---|---|---|---|
| `classic` | yes | yes | 46rem | full reference sites |
| `minimal` | drawer | hidden | wide column | longform / handbook-style docs |
| `wide` | yes | yes | app-style, fills viewport | dense API references |

## Header navigation

```php
'ui' => [
    'header' => [
        'links' => [
            ['label' => 'Guide',  'url' => '/docs/guide'],
            ['label' => 'GitHub', 'url' => 'https://github.com/you/repo', 'external' => true],
        ],
    ],
],
```

`external: true` renders a small ↗ glyph and opens the link in a new tab.

## Command palette

The header search button (and the `⌘K` / `Ctrl K` shortcut) opens a
fuzzy-filterable list of every visible page. Disable it entirely:

```dotenv
LARADOCS_SEARCH=false
```

The palette indexes the sidebar tree at render time — no external
service required.

## Edit link

Show an "Edit this page" link in the page footer pointing at your
source:

```dotenv
LARADOCS_EDIT_URL="https://github.com/you/repo/edit/main/docs/{path}"
LARADOCS_EDIT_LABEL="Edit on GitHub"
```

`{path}` is replaced by the document's path relative to `docs.path`.

## Sidebar behaviour

```php
'ui' => [
    'sidebar' => [
        'collapsible' => true, // allow nested groups to collapse
        'show_root'   => true, // include the root document as a link
    ],
],
```

## Footer

```php
'ui' => [
    'footer' => [
        'enabled' => true,
        'text'    => '© Acme — Built with Laradocs.',
        'links'   => [
            ['label' => 'Changelog', 'url' => '/docs/changelog'],
            ['label' => 'License',   'url' => '/docs/license'],
        ],
    ],
],
```

`LARADOCS_FOOTER=false` removes the footer entirely.

## Overriding views

When config tokens aren't enough, publish the Blade templates:

```bash
php artisan vendor:publish --tag=laradocs-views
```

Files land in `resources/views/vendor/laradocs/` and take precedence
over the package's own. Each page can also opt into a different layout
via `layout:` front-matter.

## Overriding assets

```bash
php artisan vendor:publish --tag=laradocs-assets
```

The CSS lives in `resources/dist/laradocs.css` and the JS in
`resources/dist/laradocs.js`. Edits to your published copies are served
under `/docs/_laradocs/asset/*`. The package does not set a
`Cache-Control` header on this route — attach your own caching
middleware if you want one.

---
title: UI
description: Branding, theming, presets, and overriding views.
order: 1
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
- **Banner** — optional full-width strip above the header for
  announcements, maintenance notices, or CTAs.
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

## Content width

The article body has a default max-width of `46rem`. Override it without touching
the CSS by setting `content_width` in config or the env var:

```dotenv
LARADOCS_CONTENT_WIDTH=80rem
```

```php
// config/laradocs.php
'ui' => [
    'content_width' => '80rem',
],
```

Any valid CSS length works — `rem`, `px`, `ch`, `%`, etc. When unset the CSS
default (`46rem`) applies unchanged.

## Presets

```dotenv
LARADOCS_UI_PRESET=classic   # classic | minimal | wide
```

| Preset | Sidebar | TOC | Content width | Best for |
|---|---|---|---|---|
| `classic` | yes | yes | 46rem (overridable) | full reference sites |
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
LARADOCS_EDIT_URL="https://github.com/you/repo/edit/main/docs/{file}"
```

The template supports three placeholders:

| Placeholder | Expands to |
|---|---|
| `{file}` | The real path on disk including the extension — `guide/routing.md`. Recommended. |
| `{path}` | Same as `{file}` but with the `.md` / `.markdown` extension stripped — `guide/routing`. Useful when you want to add the extension yourself. |
| `{ext}` | Just the extension — `md` or `markdown`. |

`{file}` correctly handles section landing pages too (`guide/_index.md`)
and works whether your docs use `.md` or `.markdown`.

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

## Banner

Display a full-width announcement strip above the header on every page —
useful for maintenance windows, version releases, or any site-wide notice.

```php
// config/laradocs.php
'ui' => [
    'banner' => [
        'enabled' => true,
        'type'    => 'info',
        'message' => '<a href="/docs/changelog">v2.0 is out</a> — see what\'s new.',
    ],
],
```

Or via environment variables:

```dotenv
LARADOCS_BANNER=true
LARADOCS_BANNER_TYPE=info
LARADOCS_BANNER_MESSAGE="Scheduled maintenance on Sunday 02:00–04:00 UTC."
```

The `message` is rendered as raw HTML, so you can include links and
inline markup for CTAs. Keep it short — one line.

| Type | Colour |
|---|---|
| `info` | Blue |
| `alert` | Amber |
| `danger` | Red |

All three variants adapt to light and dark mode automatically.

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

> **Heads up:** publishing assets takes a *snapshot* of the package CSS.
> If you previously ran `--tag=laradocs-assets` and then upgrade Laradocs,
> your published `resources/dist/laradocs.css` will **not** include newer
> styles (such as the OpenAPI method badges, parameter tables, schema tree,
> and response blocks). Re-run the command after upgrading to refresh the
> snapshot:
>
> ```bash
> php artisan vendor:publish --tag=laradocs-assets --force
> ```

## Print and PDF output

Laradocs ships a built-in `@media print` block that automatically:

- Hides navigation chrome (header, sidebar, tabs, TOC, search palette, footer,
  prev/next pager, edit link, and the reading progress bar).
- Expands `.laradocs-content` to full page width — no sidebar or TOC gutters.
- Forces light-mode colors so the output is ink-friendly regardless of the
  active theme.
- Appends `(URL)` after external links so printed copies remain useful.
- Applies `break-inside: avoid` to code blocks, callouts, tables, figures, and
  blockquotes, and `break-after: avoid` to headings to minimise awkward page
  splits.
- Sets `orphans: 3` / `widows: 3` on prose paragraphs.

**Overriding print styles** — publish the assets and add your own rules at the
bottom of `resources/dist/laradocs.css`:

```css
@media print {
  /* Example: show the sidebar nav in the printed output. */
  .laradocs-sidebar { display: block !important; }

  /* Example: hide the page breadcrumbs. */
  .laradocs-breadcrumbs { display: none !important; }

  /* Example: set explicit page margins. */
  @page { margin: 2cm; }
}
```

Any rule you add wins over the defaults via cascade source-order, so you do
not need to fork or replace the built-in block.

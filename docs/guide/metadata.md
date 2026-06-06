---
title: Metadata
description: The front-matter fields Laradocs understands.
---

# Metadata

Each page may declare YAML front-matter. Every field is optional.

```markdown
---
title: Getting Started
description: Install and configure the package.
slug: getting-started
order: 2
hidden: false
group: Basics
badge: New
icon: rocket
tags: [intro, setup]
updated_at: 2026-06-01
author: Pete
layout: docs
image: /og/getting-started.png
redirect: null
---
```

## Field reference

| Field | Type | Purpose |
|---|---|---|
| `title` | string | Display title; falls back to the filename. |
| `description` | string | Used for the `<meta name="description">` tag and the page subtitle. |
| `slug` | string | Override the URL slug — see [Routing](/docs/guide/routing). |
| `order` | int | Sort order within a section (ascending, default `999`). |
| `hidden` | bool | Hide from sidebar and listings while keeping the URL reachable. |
| `group` | string | Bucket the page under a sidebar heading and a top-level tab — see [Grouping](/docs/guide/grouping). |
| `eyebrow` | string | Small label above the page title; defaults to `group` when omitted. |
| `badge` | string | Tiny pill rendered next to the sidebar link (e.g. `New`, `Beta`). |
| `icon` | string | Free-form icon name available to custom views/macros. |
| `tags` | string\|array | Free-form labels exposed via `$document->metadata->tags`. |
| `updated_at` | string | Last-update timestamp; rendered in the page footer. |
| `author` | string | Author name; exposed to custom views. |
| `layout` | string | Override the Blade layout used to render this page. |
| `image` | string | Social / OG image URL — see [SEO](/docs/guide/seo). |
| `redirect` | string | 301-redirect this slug to another page or absolute URL. |
| `search` | bool | Set to `false` to exclude this page from the search index while keeping its URL reachable (default `true`). |
| `search_rank` | float | Rank multiplier applied to search results. Values above `1.0` boost this page; values below `1.0` demote it. Combines with the config-level `search.rank` pattern. Applies to both the JSON and Scout engines. |

> [!IMPORTANT]
> Unknown keys are preserved and reachable via
> `$document->metadata->get('your_key')` — useful for custom views or
> macros without forking the package.

> [!NOTE]
> `title`, `description`, `image`, `author` and `tags` also feed the page's
> SEO and social meta. For SEO-specific control — a different search title,
> `robots`, a `canonical` URL or a `noindex` flag — add a dedicated `seo:`
> block. See the [SEO guide](/docs/guide/seo) for the full reference.

## How fields are used

### `title`, `description`, `eyebrow`

The page header renders an eyebrow (above the title), the title (h1),
and the description (paragraph). When `eyebrow:` isn't set, the value
of `group:` is used. The description is also emitted into the page
`<meta name="description">`.

### `order`, `group`, `hidden`, `badge`

These control how the page appears in navigation:

```markdown
---
title: Caching
group: Guide
order: 3
badge: Updated
---
```

- `group:` produces both a sidebar section heading and a top-level tab — see [Grouping](/docs/guide/grouping).
- `order:` controls position within the group (lower = earlier).
- `hidden: true` removes the page from sidebar and tabs while keeping
  it reachable by URL.
- `badge:` renders a small accent pill beside the sidebar link.

### `updated_at`, `author`, `image`, `tags`

Surface metadata exposed to your templates. `updated_at` is rendered in
the page footer (`Last updated 2026-06-01`). The rest are available via
`$document->metadata->author`, `$document->metadata->image`, and
`$document->metadata->tags` — wire them into custom views as needed.

`author`, `image` and `tags` also feed the page's SEO meta (see below).

### SEO and social meta

`title`, `description`, `image`, `author` and `tags` are used to build each
page's `<title>`, meta description, Open Graph / Twitter cards and JSON-LD
automatically. For SEO-specific control that shouldn't change what renders
*on* the page, add a dedicated `seo:` block — its values win:

```markdown
---
title: Internal Notes
description: Shown as the page subtitle.
seo:
  title: A different title, just for search engines
  description: A different description, just for the meta tags.
  image: /og/custom.png
  robots: noindex, nofollow
  canonical: https://acme.test/canonical/url
  type: article
  section: Guides
---
```

| `seo:` key | Purpose |
|---|---|
| `title` | Override the SEO/social title only (the on-page `<h1>` is unchanged). |
| `description` | Override the meta / social description only. |
| `image` | Open Graph / Twitter image. |
| `author` | Author meta + schema. |
| `tags` | `article:tag` entries. |
| `robots` | Robots directive, e.g. `noindex, nofollow`. |
| `canonical` | Canonical URL for this page. |
| `type` | Open Graph type (`article`, `website`, …). |
| `section` | Open Graph `article:section`; defaults to `group`. |

Two shortcuts are also recognised at the top level: `noindex: true`
(expands to `robots: noindex, nofollow`) and `published_at:` / `date:`
(sets the Open Graph article publication time). See the full
[SEO guide](/docs/guide/seo) for site-wide defaults and examples.

### `layout`

Override the Blade layout used for this page:

```markdown
---
title: Embed
layout: vendor.acme.embed
---
```

If the layout isn't found, the default `laradocs::layout` is used.

### `redirect`

Bounce visitors elsewhere — for renames or merges. Accepts a relative
slug or an absolute URL:

```markdown
---
redirect: guide/routing
---
```

```markdown
---
redirect: https://example.com/docs/old
---
```

Relative values resolve through `route('laradocs.show', …)` so they
follow your configured route prefix.

## Custom keys

Anything not listed above ends up in `extras` and reads back via
`$document->metadata->get('key', $default)`. This is the recommended
extension point for custom view logic — define keys once in front-matter,
read them in a published Blade view.

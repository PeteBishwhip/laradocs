---
title: Metadata
description: The front-matter fields Laradocs understands.
order: 2
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
| `group` | string | Bucket the page under a sidebar heading and a top-level tab. |
| `eyebrow` | string | Small label above the page title; defaults to `group` when omitted. |
| `badge` | string | Tiny pill rendered next to the sidebar link (e.g. `New`, `Beta`). |
| `icon` | string | Free-form icon name available to custom views/macros. |
| `tags` | string\|array | Free-form labels exposed via `$document->metadata->tags`. |
| `updated_at` | string | Last-update timestamp; rendered in the page footer. |
| `author` | string | Author name; exposed to custom views. |
| `layout` | string | Override the Blade layout used to render this page. |
| `image` | string | Social / OG image URL. |
| `redirect` | string | 301-redirect this slug to another page or absolute URL. |

> [!IMPORTANT]
> Unknown keys are preserved and reachable via
> `$document->metadata->get('your_key')` — useful for custom views or
> macros without forking the package.

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

- `group:` produces both a sidebar section heading and a top-level tab.
- `order:` controls position within the group (lower = earlier).
- `hidden: true` removes the page from sidebar and tabs while keeping
  it reachable by URL.
- `badge:` renders a small accent pill beside the sidebar link.

### `updated_at`, `author`, `image`, `tags`

Surface metadata exposed to your templates. `updated_at` is rendered in
the page footer (`Last updated 2026-06-01`). The rest are available via
`$document->metadata->author`, `$document->metadata->image`, and
`$document->metadata->tags` — wire them into custom views as needed.

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

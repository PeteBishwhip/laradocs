---
title: Grouping
description: "How group: buckets sections into tabs and sidebar headings."
---

# Grouping

The `group:` front-matter field organises top-level sections into named tabs
across the top of the site and labelled headings in the left sidebar.

## How it works

Laradocs builds a navigation tree from your `docs/` directory. Each subfolder
becomes a section node; any file placed directly in `docs/` becomes a
standalone root node. Grouping acts on these *root-level* nodes only.

When a root node declares a `group:`, Laradocs:

1. Renders a horizontal **tab** for that group name in the site header.
2. Renders a labelled **sidebar heading** separating that group's entries
   from others.

All root nodes that share the same `group:` value are collected under one
tab and one sidebar heading.

## Setting a group

Add `group:` to the `_index.md` of a folder section to group the entire
section under that tab:

```markdown
---
title: Guide
group: Core
order: 1
---
```

The same field on a standalone root page works identically:

```markdown
---
title: Getting Started
group: Core
order: 2
---
```

Both of the above appear under a single **Core** tab and sidebar heading.

## Multiple sections per group

You can assign as many sections and pages as you like to the same group:

```
docs/
  getting-started.md      ← group: Core, order: 1
  guide/
    _index.md             ← group: Core, order: 2
  features/
    _index.md             ← group: Core, order: 3
  api-reference/
    _index.md             ← group: Reference, order: 1
```

This produces two tabs — **Core** (containing Getting Started, Guide, and
Features) and **Reference** (containing API Reference).

## Tab ordering

Tabs appear in the order determined by their first root node. Root nodes are
sorted by `order:` (ascending), then alphabetically by title when `order:`
values tie. The tab for a group slots into the position of that group's first
root member in the sorted list.

> [!TIP]
> Give the anchor section of each group a low `order:` value to keep the tab
> bar predictable.

## Ungrouped pages

Any root node that omits `group:` (or leaves it blank) appears in a fallback
**Overview** tab rendered at the start of the tab bar. This is the default
state for pages that don't belong to a named group. Ungrouped items do not
receive a sidebar heading.

## Nested pages

Grouping only applies to root-level nodes. Pages nested inside a folder (for
example `guide/routing.md` under the `guide/` section) are positioned by their
folder hierarchy, not their own `group:` value. Setting `group:` on a nested
page is harmless — it feeds the [eyebrow label](/docs/guide/metadata#title-description-eyebrow)
above the page title and the search index entry, but does not create a new tab
or sidebar heading.

## Config default

No default group is applied out of the box. To assign every page to a fallback
group unless it declares its own, add `group` to the `metadata.default` array
in `config/laradocs.php`:

```php
'metadata' => [
    'default' => [
        'order'  => 999,
        'hidden' => false,
        'group'  => 'Documentation',
    ],
],
```

Front-matter `group:` on individual pages always takes precedence over the
config default.

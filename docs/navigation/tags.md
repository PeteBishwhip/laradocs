---
title: Tags
description: Auto-generated tag index pages built from front-matter tags.
tags: [tags, navigation]
order: 4
---

# Tags

Any page can declare topics in its front-matter:

```yaml
---
title: Installing
tags: [getting-started, setup]
---
```

Laradocs turns those labels into browsable index pages — no extra markdown,
no configuration required. A page's tags also render as links at the foot of
the page, so a reader can jump straight from one article to everything that
shares a topic.

## The routes

Two routes are generated under your docs prefix:

| URL | What it shows |
| --- | --- |
| `/docs/tags` | A global index of every tag, each with a page count. |
| `/docs/tag/{slug}` | Every page carrying a single tag. |

Tags are matched by a slugified form of the label, so `Getting Started`,
`getting started` and `getting-started` all resolve to `/docs/tag/getting-started`
and collapse into one listing. The first spelling encountered wins as the
display label.

## Hidden pages

Listings only ever include visible pages. A page with `hidden: true` never
appears in a tag listing, and a tag used **only** by hidden pages is dropped
from the global index entirely.

## Real pages always win

The tag routes never shadow a document you authored. If a real page resolves
to `tags` (or to `tag/anything`), that page is served as normal and the
generated listing steps aside. You can also move the routes off those slugs:

```php
// config/laradocs.php
'tags' => [
    'enabled' => true,
    'index'   => 'topics',   // → /docs/topics
    'prefix'  => 'topic',    // → /docs/topic/{slug}
],
```

Set `enabled` to `false` to switch the feature — routes, page links and all —
off completely.

## Customising the look

The listings ship as publishable Blade views. Publish them with:

```bash
php artisan vendor:publish --tag=laradocs-views
```

then edit `resources/views/vendor/laradocs/tags/index.blade.php` and
`tags/show.blade.php`. Each receives ordinary Laradocs view data (the
navigation `$tree`, resolved `$variables`, SEO `$seo`) plus:

- `index.blade.php` — `$tags`, a collection of `Laradocs\Documents\Tag`
  objects, each exposing `slug`, `label` and the matching `documents`.
- `show.blade.php` — a single `$tag` with its ordered `documents`.

See [Customising the UI](/docs/customisation/ui) for the wider theming story
and [Metadata](/docs/navigation/metadata) for every front-matter field.

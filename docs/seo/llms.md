---
title: llms.txt
description: An auto-generated llms.txt that maps your documentation for language models.
order: 3
---

# llms.txt

Laradocs serves an [llmstxt.org](https://llmstxt.org)-compliant `llms.txt` at:

```
GET {prefix}/llms.txt
```

For the default prefix, that's `/docs/llms.txt`. It is a plain-text map of your
documentation: one H1 naming the site, an optional description, then one linked
bullet per page, grouped by navigation section.

The point is discoverability without crawling. A tool that wants to read your
docs makes a single HTTP request and gets the complete page list with titles,
absolute URLs and descriptions, instead of fetching your sidebar HTML and
guessing which links are documentation.

Nothing to configure: the file is built from the same document tree that renders
your sidebar and your [sitemap](/docs/seo/sitemap), so it is accurate the moment
you add a page.

## What it looks like

```
# Acme Docs

> Everything you need to build on Acme.

## Docs

- [Home](https://acme.test/docs): Start here.
- [Installation](https://acme.test/docs/installation): Install the package.

## Guide

- [Guide](https://acme.test/docs/guide): The complete walkthrough.
- [Getting Started](https://acme.test/docs/guide/getting-started): Your first page.
- [Configuration](https://acme.test/docs/guide/configuration): Every option explained.

## Reference

- [Reference](https://acme.test/docs/reference): The API surface.
- [Helpers](https://acme.test/docs/reference/helpers): Available helper functions.
```

## How it is built

**The heading.** The H1 is your site name: `seo.site_name`, falling back to
`ui.brand.title`. The blockquote underneath is your site description:
`seo.description`, falling back to `ui.brand.tagline`. When neither description
resolves the blockquote is left out entirely, which the format allows. These are
the same fallback chains the rest of your [SEO](/docs/seo) tags use, so the file
never disagrees with your `<meta>` tags.

**The sections.** One `##` heading per top-level navigation section, in the order
your sidebar shows them. A section's own `_index.md` is listed first, then its
pages, then anything nested deeper, all in tree order. Deeply nested pages are
flattened into their top-level section rather than growing an H3 tree, because
the format is a flat index and a reader wants the link, not the hierarchy.

**The leading list.** Pages that belong to no section, meaning your docs index
and any page sitting at the root of the docs path, are gathered into a single
`## Docs` list ahead of the section headings.

**The bullets.** Each entry is `- [Title](url): description`. The title is the
page title, the URL is absolute and carries the same locale and version segments
as the rest of your links, and the description is the page's front-matter
`description`. When a page declares none, Laradocs derives one from its opening
paragraph, exactly as it does for [meta descriptions](/docs/seo). When neither
yields anything the bullet is emitted as a bare link.

## What gets left out

The inclusion rules are shared with the sitemap, so the two always describe the
same site:

- pages marked `hidden: true`;
- pages carrying a `redirect:`, since an index should advertise canonical
  destinations rather than interstitials;
- with [versioning](/docs/advanced/versioning) enabled, pages of any non-default version,
  so `v1` and `v2` do not both advertise themselves as the site. Set
  `LARADOCS_SEO_SITEMAP_ALL_VERSIONS=true` to list every version instead.

A section whose every page is excluded loses its heading too, rather than
leaving an empty heading behind.

Hiding a page from `llms.txt` therefore needs no new front-matter key:

```markdown
---
title: Internal Notes
hidden: true
---
```

## Serving it from the site root

The convention points at the domain root, `/llms.txt`, not at a path inside your
docs prefix. Laradocs does not claim that route unless you ask it to: your
application may already publish an `/llms.txt` describing the whole product, of
which the docs are one part, and quietly taking the route would break it.

Opt in with one variable:

```dotenv
LARADOCS_LLMS_ROOT=true
```

The root route serves the same cached body as `{prefix}/llms.txt`, which stays
registered either way.

## Caching

`llms.txt` is cached like every other generated artifact, keyed by the combined
modification times of your documents, so it rebuilds itself the moment any page
changes and never goes stale. It is warmed by:

```bash
php artisan laradocs:cache
```

and dropped by:

```bash
php artisan laradocs:clear
```

## Configuration

| Option | Env | Default |
|---|---|---|
| `llms.enabled` | `LARADOCS_LLMS` | `true` |
| `llms.root` | `LARADOCS_LLMS_ROOT` | `false` |

Turn the whole surface off and the route is never registered:

```dotenv
LARADOCS_LLMS=false
```

The route also inherits the master docs switch: with `LARADOCS_ENABLED=false` it
returns a 404, unlike [robots.txt](/docs/seo/robots), which stays available to
tell crawlers to stay away.

## Building it yourself

The rendered file is available from the `Laradocs` service, so you can serve it
from your own route, write it to disk at deploy time, or post-process it:

```php
use Laradocs\Laradocs;

$body = app(Laradocs::class)->llmsTxt();
```

The return value is the complete file as a string, cached exactly as the route
serves it.

## Related

- [Sitemap](/docs/seo/sitemap) for crawlers that want URLs and change frequency.
- [robots.txt](/docs/seo/robots) for crawl rules.
- [MCP Server](/docs/integrations/mcp) for tools that can speak a protocol
  instead of reading a file, which gives them search and per-page fetching
  rather than a flat index.

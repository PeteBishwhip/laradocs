---
title: GET /api/search
description: Runs a full-text search and returns matching pages as JSON:API resources.
order: 2
---

# GET /api/search

Runs a full-text query over the search index and returns ranked matching pages.
Uses the same engine as the command palette — the built-in JSON index by
default, or your Scout backend when configured.

## Request

```http
GET {prefix}/_laradocs/api/search?q={query}
```

| Parameter | Type | Required | Description |
|---|---|---|---|
| `q` | string | Yes | The search query. Must be at least `search.min_chars` characters (default `2`). Shorter queries return an empty `data` array with a `200` status. |

## Response

Returns a [JSON:API 1.0](https://jsonapi.org/) document. Results are ranked:
title matches outweigh body matches, and every word in the query must appear
somewhere on the page.

### Resource: `page`

| Member | Type | Description |
|---|---|---|
| `type` | string | Always `"page"`. |
| `id` | string | The page's slug. The docs root landing page uses `"_root"`. |
| `attributes.title` | string | Display title. |
| `attributes.slug` | string | URL slug. Empty string `""` for the root landing page. |
| `attributes.url` | string | Absolute URL to the rendered page. |
| `attributes.group` | string | Value of the page's `group:` front-matter. Empty string when unset. |
| `attributes.excerpt` | string | Up to 160 characters of body text, centred on the first matched term with `…` ellipsis when clipped. Empty string for pages with no body content. |

## Example

```json
{
  "jsonapi": { "version": "1.0" },
  "links": {
    "self": "https://example.com/docs/_laradocs/api/search?q=install"
  },
  "data": [
    {
      "type": "page",
      "id": "guide/installation",
      "attributes": {
        "title": "Installation",
        "slug": "guide/installation",
        "url": "https://example.com/docs/guide/installation",
        "group": "Guide",
        "excerpt": "Run composer require to add the package to your application…"
      }
    }
  ]
}
```

## Notes

- Pages with `hidden: true` or `search: false` in front-matter are never returned.
- Queries shorter than `search.min_chars` return `"data": []` — not an error.
- `links.self` always mirrors the exact request URL including the `?q=` parameter.
- See [Search](/docs/navigation/search) to configure the active engine or change
  `min_chars` and `limit`.

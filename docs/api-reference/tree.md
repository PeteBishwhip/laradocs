---
title: GET /api/tree
description: Returns the complete navigation tree as a JSON:API compound document.
order: 1
---

# GET /api/tree

Returns the full navigation tree. Useful for building custom navigation UIs,
generating sitemaps, or feeding an MCP tool.

## Request

```http
GET {prefix}/_laradocs/api/tree
```

No query parameters.

## Response

Returns a [JSON:API 1.0](https://jsonapi.org/) compound document. Root-level
nodes are in `data`; all descendant nodes are collected into `included`. When
the tree is entirely flat (no children), `included` is omitted.

### Resource: `node`

| Member | Type | Description |
|---|---|---|
| `type` | string | Always `"node"`. |
| `id` | string | The node's slug. Always a non-empty string. |
| `attributes.title` | string | Display title. |
| `attributes.slug` | string | URL slug. |
| `attributes.url` | string\|null | Absolute URL to the rendered page. `null` for section-only nodes that have no linked document. |
| `relationships.children.data` | array | Resource linkage — each entry is `{ "type": "node", "id": "…" }`. Empty array for leaf nodes. |

## Example

A tree with one section (`guide`) containing one page (`guide/installation`):

```json
{
  "jsonapi": { "version": "1.0" },
  "links": {
    "self": "https://example.com/docs/_laradocs/api/tree"
  },
  "data": [
    {
      "type": "node",
      "id": "guide",
      "attributes": {
        "title": "Guide",
        "slug": "guide",
        "url": null
      },
      "relationships": {
        "children": {
          "data": [
            { "type": "node", "id": "guide/installation" }
          ]
        }
      }
    }
  ],
  "included": [
    {
      "type": "node",
      "id": "guide/installation",
      "attributes": {
        "title": "Installation",
        "slug": "guide/installation",
        "url": "https://example.com/docs/guide/installation"
      },
      "relationships": {
        "children": {
          "data": []
        }
      }
    }
  ]
}
```

## Notes

- Pages with `hidden: true` are excluded.
- A section node with an `_index.md` landing page has a non-null `url`.
- The docs root (`_index.md`, empty slug) is never a node in the tree — it is
  the parent of all root nodes, accessed via `GET {prefix}/`.
- All nodes in `included` carry a `relationships.children.data` array, even
  when empty, so consumers can traverse the tree uniformly.

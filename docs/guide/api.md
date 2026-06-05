---
title: API Reference
description: The HTTP JSON API and PHP facade for programmatic access to your docs.
order: 5
---

# API Reference

Laradocs exposes two surfaces for programmatic access: an **HTTP API** for
external consumers (indexers, MCP servers, headless frontends) and a **PHP API**
via the `Laradocs\Facades\Laradocs` facade.

Both respect the `laradocs.enabled` flag and the configured route prefix.

---

## HTTP API

Both HTTP endpoints are registered automatically under
`{prefix}/_laradocs/api/` and comply with
[JSON:API 1.0](https://jsonapi.org/format/). Every response is served with
`Content-Type: application/vnd.api+json`.

### Envelope

Every response shares the same top-level shape:

```json
{
  "jsonapi": { "version": "1.0" },
  "links":   { "self": "https://example.com/docs/_laradocs/api/tree" },
  "data":    [ ... ]
}
```

`data` is always an array of resource objects.

---

### `GET {prefix}/_laradocs/api/tree`

Returns the complete navigation tree. Useful for building custom navigation,
generating a sitemap, or feeding an MCP tool.

#### Resource object — `node`

| Member | Value |
|---|---|
| `type` | `"node"` |
| `id` | The node's slug (always a non-empty string) |
| `attributes.title` | Display title |
| `attributes.slug` | URL slug |
| `attributes.url` | Absolute URL to the rendered page, or `null` for section-only nodes |
| `relationships.children.data` | Array of `{ type, id }` linkage objects |

Root-level nodes appear in `data`. All descendant nodes are collected into
`included` as a compound document — `included` is omitted entirely when the
tree has no children.

#### Example

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

#### Notes

- Pages with `hidden: true` are excluded.
- Section nodes that have an `_index.md` landing page carry a non-null `url`.
- The docs root landing page (empty slug) is not included in the tree — it
  is the parent of all root nodes, not a node itself.

---

### `GET {prefix}/_laradocs/api/search?q=…`

Runs a full-text search and returns matching pages. Drives the same engine as
the command palette — the built-in JSON index by default, or your Scout backend
when configured.

#### Query parameters

| Parameter | Required | Description |
|---|---|---|
| `q` | yes | Search query. Must be at least `search.min_chars` characters (default `2`). Shorter queries return an empty `data` array. |

#### Resource object — `page`

| Member | Value |
|---|---|
| `type` | `"page"` |
| `id` | The page's slug, or `"_root"` for the docs landing page |
| `attributes.title` | Display title |
| `attributes.slug` | URL slug (empty string `""` for the root landing page) |
| `attributes.url` | Absolute URL to the rendered page |
| `attributes.group` | Value of the page's `group:` front-matter, or `""` |
| `attributes.excerpt` | Up to 160 characters of body text centred on the first matched term |

Results are ranked: title matches outweigh body matches. Every word in the
query must appear somewhere on a page for it to be returned.

#### Example

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
        "excerpt": "Run composer require laradocs/laradocs and publish the config…"
      }
    }
  ]
}
```

#### Notes

- Pages with `hidden: true` or `search: false` are never returned.
- Queries shorter than `search.min_chars` return `"data": []` with a 200 status.
- The active engine is used — see [Search](/docs/guide/search) for engine
  configuration.

---

## PHP API

The `Laradocs\Facades\Laradocs` facade exposes the core service. Use it
in service providers, console commands, or anywhere else you need to
read or manipulate the docs tree at runtime.

### Variables and macros

```php
use Laradocs\Facades\Laradocs;

Laradocs::variables(['version' => '1.0.0']);   // merge static values
Laradocs::variables(fn () => [                  // deferred until render
    'user_count' => \App\Models\User::count(),
]);

Laradocs::share('year', date('Y'));             // register a single value

Laradocs::macro('tweet', fn (array $args) => /* ... */);
```

See [Variables](/docs/features/variables) and
[Macros](/docs/features/macros) for full coverage.

### Querying the document tree

```php
$all   = Laradocs::all();               // DocumentCollection
$tree  = Laradocs::tree();              // DocumentTree
$home  = Laradocs::home();              // Document|null
$page  = Laradocs::find('guide/routing'); // Document|null
$html  = Laradocs::render($page);       // cached rendered HTML
```

#### `DocumentCollection`

A typed `Illuminate\Support\Collection<int, Document>` with a couple of
extras:

| Method | Purpose |
|---|---|
| `visible()` | Filter out `hidden: true` documents. |
| `ordered()` | Sort by `order:` then `title:`. |
| `grouped()` | Bucket by `group:` (`Collection<string, Collection>`). |
| `tagged($tag)` | Filter to docs with the given tag. |
| `findBySlug($slug)` | Locate a single doc, or `null`. |

#### `DocumentTree`

The navigation tree, built from the collection:

| Method | Returns |
|---|---|
| `rootDocument` | The `/docs` landing document (or `null`). |
| `navigation()` | Hierarchical array of `TreeNode`s (hidden nodes pruned). |
| `grouped()` | `Collection<string, Collection<int, TreeNode>>`. |

#### `Document` and `Metadata`

A document exposes:

- `$document->slug` — the resolved URL slug.
- `$document->title()` — title with filename fallback.
- `$document->html` — the rendered HTML (only present after `render()`).
- `$document->metadata` — typed `Metadata` object.
- `$document->metadata->get('key', $default)` — read any custom field.

### Variable values

```php
$values = Laradocs::variableValues();
// ['version' => '1.0.0', 'user_count' => 42, ...]
```

Closures registered with `Laradocs::variables(fn () => …)` are
evaluated exactly once per request.

### Internal services

These exist if you need them but most users won't reach for them:

- `Laradocs::variableRegistry(): VariableRegistry`
- `Laradocs::macroRegistry(): MacroRegistry`
- `Laradocs::cache(): DocumentCache`

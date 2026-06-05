---
title: API Reference
description: HTTP endpoints for programmatic access to the navigation tree and search index.
order: 8
---

# API Reference

Laradocs exposes two read-only HTTP endpoints for external consumers — indexers,
MCP servers, and headless frontends. Both live under `{prefix}/_laradocs/api/`
and comply with [JSON:API 1.0](https://jsonapi.org/).

## Endpoints

| Endpoint | Description |
|---|---|
| [`GET …/api/tree`](/docs/api-reference/tree) | Full navigation tree as a compound document. |
| [`GET …/api/search`](/docs/api-reference/search) | Full-text search over indexed pages. |

## Common behaviour

**Authentication.** The endpoints are guarded by the same middleware stack as
the rest of your docs. Add auth middleware via `route.middleware` in
`config/laradocs.php` to restrict access.

**Enabled flag.** Both endpoints return `404` when `laradocs.enabled` is
`false` — the same as all other docs routes.

**Route prefix.** The URL prefix is whatever you've configured in
`route.prefix` (default `docs`). All examples in this reference use `/docs`.

**Content type.** Every response is served with
`Content-Type: application/vnd.api+json`.

**Envelope.** Every response shares this top-level shape:

```json
{
  "jsonapi": { "version": "1.0" },
  "links":   { "self": "https://example.com/docs/_laradocs/api/…" },
  "data":    []
}
```

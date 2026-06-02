---
title: Routing
description: How file paths and metadata become URLs.
order: 1
---

# Routing

Laradocs generates a URL for every document. The strategy is configurable via
`laradocs.routing.strategy`:

- **`filename`** — the slug comes from the file path only.
- **`metadata`** — the slug comes from the front-matter `slug:` (falling back to
  the filename).
- **`both`** (default) — metadata wins when present, otherwise the filename.

## Filename slugs

```text
docs/guide/getting-started.md   ->  /docs/guide/getting-started
docs/api/_index.md              ->  /docs/api
docs/_index.md                  ->  /docs
```

## Overriding with metadata

```markdown
---
title: Authentication
slug: security/auth
---
```

The page above is served at `/docs/security/auth` regardless of where the file
lives on disk.

## Redirects

Set a `redirect:` in front-matter to send visitors elsewhere — handy when you
move or rename a page:

```markdown
---
redirect: guide/routing
---
```

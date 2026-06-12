---
title: PHP API
description: The Laradocs facade and service for programmatic access.
---

# PHP API

The `Laradocs\Facades\Laradocs` facade exposes the core service. Use it
in service providers, console commands, or anywhere else you need to
read or manipulate the docs tree at runtime.

## Variables and macros

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

## Querying the document tree

```php
$all   = Laradocs::all();               // DocumentCollection
$tree  = Laradocs::tree();              // DocumentTree
$home  = Laradocs::home();              // Document|null
$page  = Laradocs::find('guide/routing'); // Document|null
$html  = Laradocs::render($page);       // cached rendered HTML
```

### `DocumentCollection`

A typed `Illuminate\Support\Collection<int, Document>` with a couple of
extras:

| Method | Purpose |
|---|---|
| `visible()` | Filter out `hidden: true` documents. |
| `ordered()` | Sort by `order:` then `title:`. |
| `grouped()` | Bucket by `group:` (`Collection<string, Collection>`). |
| `tagged($tag)` | Filter to docs with the given tag. |
| `findBySlug($slug)` | Locate a single doc, or `null`. |

### `DocumentTree`

The navigation tree, built from the collection:

| Method | Returns |
|---|---|
| `rootDocument` | The `/docs` landing document (or `null`). |
| `navigation()` | Hierarchical array of `TreeNode`s (hidden nodes pruned). |
| `grouped()` | `Collection<string, Collection<int, TreeNode>>`. |

### `Document` and `Metadata`

A document exposes:

- `$document->slug` — the resolved URL slug.
- `$document->title()` — title with filename fallback.
- `$document->html` — the rendered HTML (only present after `render()`).
- `$document->metadata` — typed `Metadata` object.
- `$document->metadata->get('key', $default)` — read any custom field.

## Variable values

```php
$values = Laradocs::variableValues();
// ['version' => '1.0.0', 'user_count' => 42, ...]
```

Closures registered with `Laradocs::variables(fn () => …)` are
evaluated exactly once per request.

## Internal services

These exist if you need them but most users won't reach for them:

- `Laradocs::variableRegistry(): VariableRegistry`
- `Laradocs::macroRegistry(): MacroRegistry`
- `Laradocs::cache(): DocumentCache`

---
title: "Migration Guide: 0.x → 1.0"
description: Every breaking change since 0.1.0, with before/after snippets and an upgrade checklist.
order: 14
---

# Migration Guide: 0.x → 1.0

Laradocs follows [Semantic Versioning](https://semver.org): breaking changes
are reserved for major releases. This page lists **every** breaking change
introduced across the `0.x` line, consolidated for the jump to `1.0`. If
you've been tracking `0.x` releases as they shipped, you may have already
applied some of these — skim the headings and skip what you've done.

For the full list of additions and fixes alongside these, see the
[CHANGELOG](https://github.com/PeteBishwhip/laradocs/blob/main/CHANGELOG.md).

> [!TIP]
> Upgrading from a recent `0.6.x` release? You only need the
> [Frozen `TreeNode` API](#frozen-treenode-api) section below — the other
> two changes shipped in `0.1.3` and `0.3.0` and are almost certainly already
> in your codebase.

## Quick checklist

1. Replace any `docs:*` Artisan command references with `laradocs:*` — see
   [Renamed Artisan commands](#renamed-artisan-commands).
2. Rename `laradocs.seo.twitter` → `laradocs.seo.x` in your published config
   and `.env` — see [SEO config: Twitter → X](#seo-config-twitter-x).
3. If you construct or mutate `TreeNode` instances directly, switch to
   passing children through the constructor — see
   [Frozen `TreeNode` API](#frozen-treenode-api).
4. Confirm your PHP version — 1.0 requires **PHP 8.3+**. See
   [System Requirements](/docs/system-requirements).

Most consumers only touch published config, `.env`, and CI scripts that call
Artisan commands — if that's you, items 1 and 2 are a five-minute find-and-replace.
Item 3 only affects code that builds a custom navigation tree by hand.

## Renamed Artisan commands

**Since:** `0.1.3`

The `docs:` command prefix was renamed to `laradocs:` to avoid clashing with
other packages that register their own `docs:*` commands:

| Before (`< 0.1.3`) | After (`>= 0.1.3`) |
|---|---|
| `docs:install` | `laradocs:install` |
| `docs:cache` | `laradocs:cache` |
| `docs:clear` | `laradocs:clear` |

`make:doc` was left untouched — it belongs to Laravel's `make:` family rather
than the `docs:` prefix.

**Before:**

```bash
php artisan docs:cache
php artisan docs:clear
```

```php
// A deploy script or service provider calling the command programmatically
Artisan::call('docs:cache');
```

**After:**

```bash
php artisan laradocs:cache
php artisan laradocs:clear
```

```php
Artisan::call('laradocs:cache');
```

**Fix:** search your deploy scripts (`composer.json` post-deploy hooks,
CI pipelines, `Procfile`s, `Artisan::call()` sites) for `docs:install`,
`docs:cache`, `docs:clear` and rename them.

## SEO config: Twitter → X

**Since:** `0.3.0`

The SEO Twitter config was renamed to X, along with its environment variable.
The old keys are no longer read.

| Before (`0.2.x`) | After (`>= 0.3.0`) |
|---|---|
| `laradocs.seo.twitter` | `laradocs.seo.x` |
| `LARADOCS_SEO_TWITTER` | `LARADOCS_SEO_X` |
| *(none)* | `laradocs.seo.x_card` / `LARADOCS_SEO_X_CARD` (new, defaults to `summary_large_image`) |

**Before:**

```php
// config/laradocs.php
'seo' => [
    // ...
    'twitter' => env('LARADOCS_SEO_TWITTER'),
],
```

```
# .env
LARADOCS_SEO_TWITTER=laradocs
```

**After:**

```php
// config/laradocs.php
'seo' => [
    // ...
    'x' => env('LARADOCS_SEO_X'),
    'x_card' => env('LARADOCS_SEO_X_CARD', 'summary_large_image'),
],
```

```
# .env
LARADOCS_SEO_X=laradocs
```

**Fix:** re-publish the config (`php artisan vendor:publish --tag=laradocs-config --force`
and reapply your customisations, or hand-edit the `seo` block) and rename the
`.env` entry. See [SEO](/docs/seo) for the full config reference.

## Frozen `TreeNode` API

**Since:** the `1.0` API freeze

`Document`, `DocumentTree`, `DocumentCollection`, `Tag` and `Metadata` were
already effectively immutable in `0.x` (their constructor properties were
`readonly`), so most consumers see no change. `TreeNode` is the exception: its
properties were plain `public` and it exposed two mutator methods used while
`DocumentTree` assembled the navigation tree. Both are gone in `1.0` —
`TreeNode` is now `readonly` throughout, and a node's `children` are supplied
once, at construction time.

| Removed | Replacement |
|---|---|
| `TreeNode::addChild(TreeNode $child): void` | Pass the full `children` array to the constructor. |
| `TreeNode::sortChildren(): void` | Not needed — `DocumentTree::fromDocuments()` sorts children before constructing each node. |
| `$node->title = '...'` (property mutation) | Construct a new `TreeNode` instead. |

This only affects code that builds a `TreeNode` tree by hand — for example a
custom `DocumentLoader`, a test helper, or a hand-rolled navigation renderer.
If you only ever *read* `TreeNode`s returned from `Laradocs::tree()` or
`DocumentTree::navigation()`, nothing changes for you.

**Before:**

```php
use Laradocs\Documents\TreeNode;

$section = new TreeNode('Guide', 'guide');
$section->addChild(new TreeNode('Routing', 'guide/routing'));
$section->addChild(new TreeNode('Caching', 'guide/caching'));
$section->sortChildren();
```

**After:**

```php
use Laradocs\Documents\TreeNode;

$section = new TreeNode('Guide', 'guide', children: [
    new TreeNode('Caching', 'guide/caching'),
    new TreeNode('Routing', 'guide/routing'),
]);
```

**Fix:** build the full `children` array before constructing the parent
`TreeNode`, and sort it yourself (`usort` by whatever order you need) before
passing it in — there's no post-construction sort step anymore. See the
[PHP API](/docs/http-api/php) reference for the current shape of `Document`,
`DocumentTree` and `TreeNode`.

## Worth knowing, not breaking

These changes ship in the `0.x` → `1.0` window too, but neither requires code
changes for most consumers — listed here so nothing catches you by surprise.

- **Locale in the URL path (`0.6.0`).** When two or more locales are
  available, the active locale now defaults to living in the path
  (`/docs/fr/guide`) instead of a `?lang=` query string. Legacy `?lang=<code>`
  URLs 301-redirect to the new form automatically. If you've hardcoded
  `?lang=` links anywhere outside the package (custom nav, an external site),
  update them, or set `LARADOCS_LOCALE_URL=false` to keep the old behaviour.
  See [Localisation](/docs/advanced/localisation).
- **PHP 8.3 minimum.** Somewhere after `0.6.1`, on the way to `1.0`, the
  supported PHP floor moved from 8.2 to 8.3. See
  [System Requirements](/docs/system-requirements) for the full PHP/Laravel
  compatibility matrix.
- **`docs.path` / `cache.key_prefix` resolved per-request (`0.3.0`).** These
  config values used to be baked into the container at boot; they're now
  resolved fresh on each request so multi-tenant apps can retarget them
  without rebuilding the container. Reading them at boot time still works —
  this only matters if you were caching the *old* value yourself.

## Getting help

If you hit a breaking change not covered here, please open an issue —
migration reports from real upgrades are what keep this page accurate.

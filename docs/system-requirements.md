---
title: System Requirements
description: PHP, Laravel, and extension requirements for running Laradocs.
order: 2
---

# System Requirements

## PHP

Laradocs requires **PHP 8.3 or higher**. The following extensions must be
enabled (they are on by default in most PHP distributions):

- `dom`
- `libxml`
- `mbstring`

## Laravel

Laradocs supports the three most recent major releases of Laravel:

| Laravel | Minimum version |
|---------|----------------|
| 11      | 11.14          |
| 12      | 12.0           |
| 13      | 13.0           |

## PHP / Laravel compatibility matrix

The table below reflects the combinations tested in CI. Cells marked ✓ are
tested on both `prefer-stable` and `prefer-lowest` dependency resolutions.

| PHP version | Laravel 11 | Laravel 12 | Laravel 13 |
|-------------|:----------:|:----------:|:----------:|
| 8.3         | ✓          | ✓          | ✓          |
| 8.4         | ✓          | ✓          | ✓          |
| 8.5         | ✓          | ✓          | ✓          |

## Optional packages

Some Laradocs features require additional packages that are not installed by
default:

| Package | Feature |
|---------|---------|
| `laravel/scout` | Full-text search powered by Meilisearch, Typesense, or Algolia — see [Search](/docs/guide/search) |
| `simonhamp/the-og` | Automatic Open Graph / social card image generation — see [Open Graph images](/docs/guide/seo#open-graph-images) |
| `nyholm/psr7` | PSR-17 HTTP factory required by the Meilisearch SDK when using Scout — see [Search](/docs/guide/search#laravel-scout) |

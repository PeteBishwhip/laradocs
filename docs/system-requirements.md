---
title: System Requirements
description: PHP, Laravel, and extension requirements for running Laradocs.
order: 3
---

# System Requirements

## PHP

This backport requires **PHP 7.3 or higher** and targets Laravel 8. The following extensions must be
enabled (they are on by default in most PHP distributions):

- `dom`
- `libxml`
- `mbstring`

## Laravel

This package supports Laravel 8:

| Laravel | Minimum version |
|---------|----------------|
| 8       | 8.0            |

## PHP / Laravel compatibility matrix

The table below reflects the combinations tested in CI. Cells marked ✓ are
tested on both `prefer-stable` and `prefer-lowest` dependency resolutions.

| PHP version | Laravel 8 |
|-------------|:---------:|
| 7.3         | ✓         |

## Optional packages

Some Laradocs features require additional packages that are not installed by
default:

| Package | Feature |
|---------|---------|
| `devizzent/cebe-php-openapi` | OpenAPI 3.0/3.1 spec parsing and native reference page rendering — see [OpenAPI](/docs/content/openapi) |
| `symfony/yaml` | YAML serialisation used by the `laradocs:openapi` spec generator command — see [OpenAPI](/docs/content/openapi#generating-a-spec-from-your-routes) |
| `laravel/scout` | Full-text search powered by Meilisearch, Typesense, or Algolia — see [Search](/docs/guide/search) |

## Backport limitations

- MCP registration is disabled unless a compatible implementation is supplied; `laravel/mcp` requires newer PHP and Laravel versions.
- The Scramble OpenAPI driver and `simonhamp/the-og` generator are optional and are not installed because their current releases do not support PHP 7.3 / Laravel 8.
- The built-in JSON search, native OpenAPI parser/generator, SEO metadata renderer, sitemap and feeds remain available.

---
title: Caching
description: Fast rendering with automatic invalidation.
order: 3
---

# Caching

Rendering markdown to HTML is cached. Cache keys embed each file's modification
time, so editing a file automatically invalidates its cached HTML — no manual
clearing during development.

## Commands

```bash
# Pre-render and cache every page (great for deploys)
php artisan laradocs:cache

# Clear all cached documentation
php artisan laradocs:clear
```

Laradocs also hooks into Laravel's optimizer:

```bash
php artisan optimize         # warms the docs cache
php artisan optimize:clear   # clears it
```

## Configuration

```php
'cache' => [
    'enabled' => env('LARADOCS_CACHE', true),
    'store' => env('LARADOCS_CACHE_STORE'), // null = default store
    'ttl' => env('LARADOCS_CACHE_TTL', 86400),
    'key_prefix' => 'laradocs',
],
```

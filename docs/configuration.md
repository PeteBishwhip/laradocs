---
title: Configuration
description: Every Laradocs option and the environment variables that drive it.
order: 3
---

# Configuration

Publish the config file with `php artisan vendor:publish --tag=laradocs-config`.
It lands at `config/laradocs.php`. Every important value is also driven by an
environment variable so you can change behaviour per-environment.

| Option | Env | Default |
|---|---|---|
| Master switch | `LARADOCS_ENABLED` | `true` |
| Route prefix | `LARADOCS_ROUTE_PREFIX` | `docs` |
| Route domain | `LARADOCS_ROUTE_DOMAIN` | `null` |
| Docs path | `LARADOCS_PATH` | `base_path('docs')` |
| Routing strategy | `LARADOCS_ROUTING_STRATEGY` | `both` |
| Theme | `LARADOCS_THEME` | `auto` |
| Caching | `LARADOCS_CACHE` | `true` |

## Disabling docs in production

```php
// .env
LARADOCS_ENABLED=false
```

When disabled, no routes are registered and every docs URL returns `404`.

> [!WARNING]
> The route prefix is read when routes are registered (at boot). After changing
> it, clear your route cache with `php artisan route:clear`.

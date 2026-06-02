---
title: Customising the UI
description: Branding, theming and overriding views.
order: 6
group: Advanced
---

# Customising the UI

## Branding

```php
// config/laradocs.php
'ui' => [
    'theme' => 'auto', // auto | light | dark
    'brand' => [
        'title' => 'Acme Docs',
        'logo' => '/img/logo.svg',
        'favicon' => '/favicon.ico',
    ],
],
```

## Dark mode

The bundled UI supports light, dark and system themes with a toggle in the
header. The choice is remembered in `localStorage`.

## Overriding views

Publish the Blade views and edit them freely:

```bash
php artisan vendor:publish --tag=laradocs-views
```

Files land in `resources/views/vendor/laradocs` and take precedence over the
package's own templates.

## Overriding assets

```bash
php artisan vendor:publish --tag=laradocs-assets
```

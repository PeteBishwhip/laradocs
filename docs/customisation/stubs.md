---
title: Stubs
description: Override the scaffolds that laradocs:install and make:doc write.
order: 2
---

# Customising stubs

`laradocs:install` and `make:doc` both write markdown files from Blade templates
that ship with the package. Publish those templates to control exactly what
new pages look like in your project.

## Publishing

```bash
php artisan vendor:publish --tag=laradocs-stubs
```

Two files land in `stubs/laradocs/`:

| File | Used by | Receives |
|---|---|---|
| `welcome.blade.php` | `laradocs:install` | _(no variables)_ |
| `page.blade.php` | `make:doc` | `$title`, `$group`, `$order`, `$name` |

The commands always prefer a published stub over the package's copy, so any
file you leave in `stubs/laradocs/` wins. Delete a file to fall back to the
package default.

> [!NOTE]
> The stubs are Blade templates that produce plain `.md` output. The
> `.blade.php` extension only describes the template — the file written to
> `docs/` is still a regular markdown file.

## Anatomy of a stub

Stubs are compiled with `Blade::render()`, so the full Blade syntax is
available — `{{ $title }}` for echoes, `@if`/`@foreach` for control flow,
`{!! ... !!}` for raw output. Here is the `make:doc` default trimmed for
clarity:

```blade
---
title: {{ $title }}
{!! $group !== null ? "group: {$group}" : '# group: Guides' !!}
{!! $order !== null ? "order: {$order}" : '# order: 1' !!}
# description: A short summary used for <meta> tags and SEO
# slug: custom-url
# hidden: true
# ... every other metadata field, commented out
---

# {{ $title }}

Start writing your documentation here.
```

The inline ternary keeps the rendered front-matter free of blank lines when
`--group` or `--order` aren't supplied.

## Recipes

### Force every page into a group

```blade
---
title: {{ $title }}
group: {{ $group ?? 'Uncategorised' }}
{!! $order !== null ? "order: {$order}" : '' !!}
---

# {{ $title }}
```

### Stamp every new page with the date

```blade
---
title: {{ $title }}
updated_at: {{ now()->toDateString() }}
author: {{ config('app.author', 'Docs Team') }}
---

# {{ $title }}

<!-- created via `php artisan make:doc {{ $name }}` -->
```

### Pre-populate body content

````blade
---
title: {{ $title }}
---

# {{ $title }}

## Overview

Describe what this page covers.

## Example

```php
// code goes here
```

## See also

- [Related page](/docs/related)
````

## Available metadata

Every key on the [Metadata](/docs/navigation/metadata) page is valid in a stub.
The default stub keeps each one commented out so new authors can uncomment
the fields they need without consulting the docs.

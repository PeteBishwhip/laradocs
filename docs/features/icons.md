---
title: Icons
description: Inline icons in page content and navigation using Heroicons or a custom set.
order: 5
---

# Icons

Laradocs has first-class support for icons in two places:

- **Navigation** — set `icon:` in a page's front-matter and the icon appears next to the title in the sidebar and in the page `<h1>`.
- **Body content** — embed icons inline anywhere in your markdown with the `@icon()` shorthand or the `@docs('icon', ...)` macro.

Icons are rendered as inline SVG so they inherit the surrounding text colour, scale with `em`-based sizing, and work without any additional HTTP requests.

## Requirements

The built-in `heroicons` driver reads SVG files from the
[Heroicons](https://github.com/tailwindlabs/heroicons) npm package. Install it
in your project root:

```bash
npm install heroicons
```

The package is auto-detected under `node_modules/heroicons/`. If you install it
elsewhere, set the path explicitly — see [configuration](#configuration) below.

When the package is missing, icons render as nothing rather than erroring. To
catch that early — and to catch typo'd icon names — run the linter, which
validates every icon reference:

```bash
php artisan docs:lint
```

See [Linting icon references](#linting-icon-references) below.

## Front-matter icon

Add an `icon:` key to any document's front-matter to display an icon
alongside the title:

```markdown
---
title: Getting Started
icon: arrow-long-right
---

Content here.
```

The icon name is the kebab-case Heroicons name (e.g. `arrow-long-right`,
`check-circle`, `x-mark`). It appears:

- In the **sidebar** nav link, before the page title.
- In the **page header** `<h1>`, before the title text.

Section index pages (`_index.md`) also support `icon:` — the icon shows in the
section heading row of the sidebar.

## Inline icons in content

### `@icon()` shorthand

Use `@icon('name')` anywhere in your markdown body to render an icon inline:

```markdown
Click the @icon('plus') button to add a new entry.
```

Add a `variant:` argument to choose the icon style:

```markdown
This is done. @icon('check', variant: 'solid')
```

To use a specific icon set, pass `set:`:

```markdown
@icon('arrow-right', set: 'heroicons')
```

### `@docs('icon', ...)` macro

Icons are also available through the standard macro syntax, which is useful when
you prefer to be explicit or when composing with other macro tooling:

```markdown
@docs('icon', 'arrow-long-right')
@docs('icon', 'check', variant: 'solid')
@docs('icon:heroicons', 'arrow-long-right')
```

`@docs('icon:heroicons', ...)` always uses Heroicons regardless of the
configured default driver.

### Inside code — no expansion

`@icon()` calls inside fenced code blocks and inline `` `code` `` spans are
left verbatim so you can document them literally without them rendering:

````markdown
Use `@icon('name')` to render an icon inline.
````

## Heroicons variants

Heroicons ships four sizes and styles. Pass the variant name as the second
argument to `@icon()` or as the `variant:` named argument:

| Variant | Size | Style |
|---|---|---|
| `outline` | 24 × 24 px | Stroke (default) |
| `solid` | 24 × 24 px | Filled |
| `mini` | 20 × 20 px | Filled, compact |
| `micro` | 16 × 16 px | Filled, extra-compact |

```markdown
Outline (default):  @icon('check-circle')
Solid:              @icon('check-circle', variant: 'solid')
Mini:               @icon('check-circle', variant: 'mini')
Micro:              @icon('check-circle', variant: 'micro')
```

Unknown variant names silently fall back to `outline`.

## Styling icons

Icons are wrapped in `<span class="laradocs-icon" aria-hidden="true">`. Target
that class in your CSS to control size, colour, or vertical alignment:

```css
.laradocs-icon svg {
    display: inline-block;
    width: 1em;
    height: 1em;
    vertical-align: -0.125em;
}
```

Because the SVG inherits `currentColor`, changing the surrounding text colour
changes the icon colour automatically.

## Custom icon sets

Register any additional icon set from a service provider using the `Laradocs`
facade. The closure receives the icon name and variant string and must return a
raw SVG string, or an empty string when the icon is not found:

```php
use Laradocs\Facades\Laradocs;

Laradocs::registerIconSet('phosphor', function (string $name, string $variant): string {
    $path = resource_path("icons/phosphor/{$name}.svg");

    return file_exists($path) ? file_get_contents($path) : '';
});
```

Once registered, use the set by name:

```markdown
@icon('arrow-right', set: 'phosphor')
@docs('icon', 'arrow-right', set: 'phosphor')
```

To make a custom set the default (so `@icon('name')` uses it without `set:`),
change the driver in your config:

```php
// config/laradocs.php
'icons' => [
    'driver' => 'phosphor',
],
```

## Linting icon references

Because icons resolve at render time, a missing dependency (or a typo'd icon
name) fails silently — the icon simply renders as nothing. The `docs:lint`
command catches this by validating every icon reference across your docs: the
`icon:` front-matter field and inline `@icon()` calls in the body.

```bash
php artisan docs:lint
```

Each unresolved reference is reported with a reason:

- **Set unavailable** — the icon set is not registered. For the built-in
  `heroicons` set this almost always means the npm package isn't installed, so
  the message includes the `npm install heroicons` hint.
- **Unknown icon** — the set is available but has no icon by that name (usually
  a typo or a glyph that was renamed/removed).

Calls inside fenced code blocks and inline `` `code` `` are ignored, so
documented examples are never flagged. Machine-readable output is available via
`docs:lint --json` under the `unresolved_icons` key.

Disable the check (for example in CI environments without `node_modules`) by
setting `lint.icons` to `false`:

```php
// config/laradocs.php
'lint' => [
    'icons' => false,
],
```

## Configuration

```php
// config/laradocs.php

'icons' => [
    // The default icon set. Built-in value: "heroicons".
    // Set to null to disable automatic rendering.
    'driver' => env('LARADOCS_ICONS_DRIVER', 'heroicons'),

    'heroicons' => [
        // Absolute path to the heroicons package directory.
        // Leave null to auto-detect from node_modules/heroicons.
        'path' => env('LARADOCS_HEROICONS_PATH'),

        // Default variant when none is specified: outline | solid | mini | micro
        'variant' => env('LARADOCS_HEROICONS_VARIANT', 'outline'),
    ],
],
```

The `@icon()` shorthand can be disabled independently of the macro system by
setting `parser.extensions.icons` to `false` in the parser extensions config.
The `@docs('icon', ...)` macro will still work when icons are disabled via the
extension flag, as long as the macro registry has the icon handler registered.

| Option | Env | Default |
|---|---|---|
| `icons.driver` | `LARADOCS_ICONS_DRIVER` | `heroicons` |
| `icons.heroicons.path` | `LARADOCS_HEROICONS_PATH` | auto-detect |
| `icons.heroicons.variant` | `LARADOCS_HEROICONS_VARIANT` | `outline` |
| `parser.extensions.icons` | — | `true` |

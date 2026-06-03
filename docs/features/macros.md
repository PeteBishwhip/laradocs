---
title: Macros
description: Reusable content components for your docs.
order: 2
---

# Macros

Macros are reusable snippets invoked with the `@docs(...)` syntax. The
package ships with four built-ins and you can register as many of your
own as you like.

## Calling syntax

```markdown
@docs('name', arg: 'value', other: 42)
@docs('name', 'first positional', 'second positional')
```

- The first argument is always the macro name.
- Subsequent arguments may be **named** (`arg: 'value'`) or **positional**.
- Bare scalars are coerced: `true` / `false` become booleans, integers stay
  integers, everything else is a string.
- Macro calls inside fenced code blocks and inline `` `code spans` `` are
  left alone so you can document them literally.

## Built-in macros

| Macro | Named args | Positional args | Renders |
|---|---|---|---|
| `alert` | `type`, `body` | `body` | Coloured alert box. |
| `badge` | `text` | `text` | Small pill label. |
| `button` | `text`, `href` | `text`, `href` | Styled call-to-action button. |
| `embed` | `url` | `url` | Safe-URL link wrapper for raw URLs. |

### Alert

```markdown
@docs('alert', type: 'warning', body: 'Back up your database first.')
```

@docs('alert', type: 'warning', body: 'Back up your database first.')

`type:` matches a CSS class on the alert (`info`, `tip`, `warning`,
`danger`, etc. — extend by publishing the view). When omitted, defaults
to `info`.

### Badge

```markdown
@docs('badge', text: 'Beta')
```

@docs('badge', text: 'Beta')

### Button

```markdown
@docs('button', text: 'Download', href: '/downloads')
```

@docs('button', text: 'Download', href: '/downloads')

`href` is passed through the URL safety guard, so `javascript:` and
`data:` URIs are blocked.

### Embed

```markdown
@docs('embed', url: 'https://example.com')
```

A thin safe-URL wrapper — useful when you want to surface a raw URL
without manual escaping.

## Registering your own

A macro handler is either a Blade view name or a closure returning HTML:

```php
use Laradocs\Facades\Laradocs;

Laradocs::macro('tweet', fn (array $arguments) => sprintf(
    '<a class="tweet" href="https://twitter.com/%s">@%s</a>',
    e($arguments['user']),
    e($arguments['user']),
));
```

```markdown
@docs('tweet', user: 'laravelphp')
```

Or via config (Blade views only):

```php
// config/laradocs.php
'macros' => [
    'tweet' => 'partials.tweet',
],
```

The view receives the named arguments as compact variables and the full
positional list as `$arguments`:

```blade
{{-- resources/views/partials/tweet.blade.php --}}
<a class="tweet" href="https://twitter.com/{{ $user }}">@{{ $user }}</a>
```

## Safety

Macro arguments are passed verbatim into the handler — escape user
input with `e(...)` (or your view's `{{ }}`) before rendering. The
built-in `button` and `embed` macros run their URLs through
`Laradocs\Support\Url::safe(...)` which strips dangerous schemes.

## Publishing the built-ins

Want to restyle the bundled macros? Publish the views and edit them:

```bash
php artisan vendor:publish --tag=laradocs-views
```

The macro templates land at `resources/views/vendor/laradocs/macros/`.

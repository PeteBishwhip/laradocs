---
title: Macros
description: Reusable content components for your docs.
order: 2
---

# Macros

Macros are reusable snippets invoked with the `@docs()` syntax. Laradocs ships
with a few built-ins and you can register your own.

## Built-in macros

```markdown
@docs('alert', type: 'warning', body: 'Back up your database first.')
@docs('badge', text: 'Beta')
@docs('button', text: 'Download', href: '/downloads')
```

Rendered:

@docs('alert', type: 'warning', body: 'Back up your database first.')
@docs('badge', text: 'Beta')

## Registering your own

A macro handler is either a Blade view name or a closure returning HTML:

```php
use Laradocs\Facades\Laradocs;

Laradocs::macro('tweet', fn (array $arguments) => sprintf(
    '<a class="tweet" href="https://twitter.com/%s">@%s</a>',
    $arguments['user'],
    $arguments['user'],
));
```

```markdown
@docs('tweet', user: 'laravelphp')
```

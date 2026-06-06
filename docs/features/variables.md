---
title: Variables
description: Interpolate dynamic values into your docs.
---

# Variables

Reference a variable anywhere in your markdown with double braces:

```markdown
The current version is {{ version }}.
```

Variables come from two places: the config file, and your service
providers.

## Static variables (config)

```php
// config/laradocs.php
'variables' => [
    'version'       => '1.0.0',
    'support_email' => 'support@example.com',
],
```

## Dynamic variables (service provider)

Register values — including closures — from any service provider:

```php
use Laradocs\Facades\Laradocs;

public function boot(): void
{
    Laradocs::variables(fn () => [
        'app_name'   => config('app.name'),
        'user_count' => \App\Models\User::count(),
    ]);

    Laradocs::share('year', date('Y'));
}
```

- `Laradocs::variables(array|Closure)` merges new values into the
  registry. Pass a closure to defer the work until render time.
- `Laradocs::share(string, mixed)` registers a single value.

## Dotted keys

Variables resolve into nested arrays using dot notation:

```php
'variables' => [
    'app' => [
        'name' => 'Acme',
        'url'  => 'https://acme.test',
    ],
],
```

```markdown
Visit [{{ app.name }}]({{ app.url }}).
```

## Escaping and unknown variables

> [!NOTE]
> Variables inside fenced code blocks and inline `` `code spans` `` are
> left untouched, so you can still document literal `{{ braces }}` in
> examples.

How unknown variables render is configurable:

```php
'parser' => [
    'unknown_variable' => 'blank', // blank | raw
],
```

- `blank` (default) — `{{ nope }}` renders nothing.
- `raw` — the literal braces are kept in the output.

## Security

Variable values are HTML-escaped at render time, so untrusted values
can't smuggle in stored XSS. If you're rendering a URL, also pipe it
through `Laradocs\Support\Url::safe(...)` to neutralise `javascript:`
and `data:` schemes before adding it to the registry.

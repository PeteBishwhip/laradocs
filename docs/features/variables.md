---
title: Variables
description: Interpolate dynamic values into your docs.
order: 1
---

# Variables

Reference a variable anywhere in your markdown with double braces:

```markdown
The current version is {{ version }}.
```

Variables come from two places.

## Static variables (config)

```php
// config/laradocs.php
'variables' => [
    'version' => '1.0.0',
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
        'app_name' => config('app.name'),
        'user_count' => \App\Models\User::count(),
    ]);

    Laradocs::share('year', date('Y'));
}
```

> [!NOTE]
> Variables inside code blocks and inline code are left untouched, so you can
> still document literal `{{ braces }}` in examples.

Dotted keys read into nested arrays: `{{ app.name }}`.

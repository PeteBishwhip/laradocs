---
title: Testing
description: How Laradocs is tested, and how to test your own docs.
order: 7
group: Advanced
---

# Testing

Laradocs is developed with [Pest](https://pestphp.com) on top of
[Orchestra Testbench](https://github.com/orchestral/testbench) and Workbench,
with a 100% coverage gate, PHPStan at the maximum level and Laravel Pint.

```bash
composer test            # pint --test + phpstan + pest
composer test:coverage   # pest --coverage --min=100
```

## Testing your own documentation

Because docs are just routes, you can assert on them in your app's test suite:

```php
it('publishes the changelog', function () {
    $this->get('/docs/changelog')
        ->assertOk()
        ->assertSee('Unreleased');
});
```

> [!TIP]
> Point `LARADOCS_PATH` at a fixtures directory in your test environment to
> assert against a known set of pages.

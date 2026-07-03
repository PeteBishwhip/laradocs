---
title: Testing
description: How Laradocs is tested, and how to test your own docs.
order: 6
---

# Testing

Laradocs is developed with [Pest](https://pestphp.com) on top of
[Orchestra Testbench](https://github.com/orchestral/testbench) and Workbench,
with a 100% coverage gate, PHPStan and Psalm at the maximum level, and Laravel
Pint.

```bash
composer test            # pint --test + phpstan + psalm + pest
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

## End-to-end tests

A [Playwright](https://playwright.dev) suite in `tests/e2e/` drives the rendered
documentation site in a real browser, covering navigation, search, the on-page
table of contents, theming, the sitemap, and more.

Install the runner and its browser once:

```bash
npm ci
npx playwright install --with-deps chromium
```

Then run the suite:

```bash
npm run test:e2e      # headless run
npm run test:e2e:ui   # interactive Playwright UI
```

The suite boots the docs site itself via Testbench, so no server needs to be
running beforehand.

### Coverage gate

Every user-facing feature is declared in `tests/e2e/features.json`, which maps a
feature name to the spec that must cover it. A `coverage.spec.ts` test reads this
registry and fails if any declared feature is missing its spec file. Adding a new
feature to the registry without writing its spec therefore fails the build —
keeping the suite honest as the site grows.

### Projects

Playwright runs two projects. The **default** project exercises the standard
site, while the **banner** project boots a second instance with the global
banner enabled (via the `LARADOCS_BANNER*` environment variables) so the banner
can be asserted in isolation. `banner.spec.ts` runs only under the banner
project.

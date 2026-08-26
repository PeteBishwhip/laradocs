# Variables, macros, the facade and configuration

## Variables

Variables interpolate into content with `{{ key }}` or `{{ nested.key }}`, and
are ignored inside code spans and fenced blocks. Register static values in
`config('laradocs.variables')`, dynamic ones via the facade.

```markdown
The current release is {{ app_version }}. Support email: {{ support.email }}.
```

## Macros

Macros are reusable named blocks. Invoke them with `@docs('name', ...)` or the
equivalent component syntax `<x-name attr="value">slot</x-name>` — the two
round-trip. Arguments may be positional or named, and bare scalars are coerced
(`true`/`false` to bool, integers stay ints, otherwise string).

Built-in macros: `alert`, `badge`, `button`, `callout`, `embed`.

```markdown
@docs('alert', type: 'warning', body: 'Back up your database first.')

@docs('button', text: 'Get started', href: '/docs/getting-started')

<x-callout type="tip" title="Pro tip">
Every macro is also callable as a component.
</x-callout>
```

Register custom macros in `config('laradocs.macros')` (mapping a name to a Blade
view) or via the facade (a closure or a view name).

## Facade API

```php
use Laradocs\Facades\Laradocs;

public function boot(): void
{
    Laradocs::variables(fn () => [
        'app_version' => config('app.version'),
        'support' => ['email' => 'help@example.com'],
    ]);

    Laradocs::share('build', 'edge');

    Laradocs::macro('youtube', fn (string $id) => view('macros.youtube', ['id' => $id]));

    Laradocs::rateLimit(120);                                  // API rpm per IP (or false to disable)
    Laradocs::cookiesEnabled(fn () => Cookie::get('consent') === 'true');
}
```

Configuration methods: `variables(array|Closure)`, `share(key, value)`,
`macro(name, Closure|string)`, `rateLimit(Closure|int|false)`,
`cookiesEnabled(?Closure)`.

Read API: `all()`, `tree()`, `find(slug)`, `home()`, `tags()`, `tag(slug)`,
`render($document)`, `searchIndex()`, `sitemap()`,
`feed($format, $limit, $feedUrl, $siteTitle)`, `variableValues()`.

> [!IMPORTANT]
> Static `config('laradocs.variables')` and `config('laradocs.macros')` must be
> cache-safe — **no closures in config files**, they break `config:cache`. Use
> the facade in a service provider for anything dynamic.

## Configuration & publishing

All configuration lives in `config/laradocs.php`. Common env vars include
`LARADOCS_ENABLED` (master switch), `LARADOCS_ROUTE_PREFIX` (default `docs`),
`LARADOCS_THEME` (`auto`/`light`/`dark`) and `LARADOCS_SITE` (hosted deploys).

```bash
php artisan vendor:publish --tag=laradocs-config   # config/laradocs.php
php artisan vendor:publish --tag=laradocs-views    # resources/views/vendor/laradocs/
php artisan vendor:publish --tag=laradocs-lang     # translation files
php artisan vendor:publish --tag=laradocs-assets   # compiled CSS/JS
php artisan vendor:publish --tag=laradocs-stubs    # make:doc page stub
php artisan vendor:publish --tag=laradocs-all      # everything above
```

Config areas you may need to touch: `route` (prefix, domain, middleware), `docs`
(path, extensions, ignored patterns), `routing` (slug strategy), `parser`
(extensions, highlighter, TOC), `ui` (theme, preset, accent, brand), `seo`,
`search`, `cache`, `tags`, `versions` (multi-version docs) and `locale`.

Published views take precedence over the package's own, so `composer update`
will not bring upstream changes to a view you have published. Diff them against
`vendor/petebishwhip/laradocs/resources/views/` after upgrading.

## SEO, search and feeds

- **SEO** — every page is served with a `<title>`, meta description, Open Graph
  and Twitter/X cards, canonical URL and JSON-LD, derived from the page's
  content and front-matter. Override per page with `title`, `description`,
  `image` and `author` (or a `seo:` block). A 1200×630 OG card is generated when
  a page declares no image.
- **Sitemap** — `{prefix}/sitemap.xml` lists every visible, non-redirected page.
- **Feed** — RSS 2.0 or Atom 1.0 of the most-recently-updated pages at
  `{prefix}/feed.xml` (`laradocs.feed`).
- **Search** — powers the ⌘K palette. Driver is `auto` (Scout when available,
  else the built-in JSON index), `scout` or `json`. Exclude a page with
  `search: false`; hidden pages are never indexed.

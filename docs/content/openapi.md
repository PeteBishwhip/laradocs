---
title: OpenAPI
description: Generate interactive API reference pages from an OpenAPI 3.0/3.1 spec.
order: 6
---

# OpenAPI

Laradocs can render a full API reference section straight from an OpenAPI
3.0 or 3.1 specification that lives alongside your docs. Every operation becomes
a navigable page — complete with method and path, parameters, request and
response schemas (with `$ref`, `allOf`, `oneOf`, `anyOf` and `enum` expanded),
and a populated table of contents. The pages flow through the same sidebar,
search, sitemap, SEO and localisation pipeline as your hand-written Markdown.

## Opting in

The integration is **disabled by default**. Turn it on in two steps.

### 1. Install the parser

The spec is parsed by the optional
[`devizzent/cebe-php-openapi`](https://github.com/devizzent/cebe-php-openapi)
package, which is not pulled in automatically:

```bash
composer require devizzent/cebe-php-openapi
```

### 2. Enable it and drop in a spec

Flip the `openapi.enabled` flag — either in `config/laradocs.php` or with the
`LARADOCS_OPENAPI` environment variable — and place a spec file in your docs
directory:

```dotenv
LARADOCS_OPENAPI=true
```

```
resources/docs/
├── _index.md
├── getting-started.md
└── openapi.yaml      ← your spec (yaml, yml or json)
```

By default Laradocs looks for `openapi.yaml`, `openapi.yml` or `openapi.json` in
each docs source. The first match is mounted under `/docs/api`, with one
overview page plus one page per operation grouped by its first tag.

> [!NOTE]
> When multi-version docs are enabled, drop a spec into each version directory
> and the matching version's reference section is rebuilt automatically — the
> spec is parsed once and cached by path + modification time, so edits are
> picked up without a manual cache clear.

## Configuration

All options live under the `openapi` key of `config/laradocs.php`:

| Option | Default | Description |
|---|---|---|
| `enabled` | `false` | Master switch for the integration (env: `LARADOCS_OPENAPI`). |
| `files` | `['openapi.yaml', 'openapi.yml', 'openapi.json']` | Candidate spec filenames searched for in each docs source. |
| `base_slug` | `api` | URL segment the reference pages mount under (e.g. `/docs/api`). |
| `title` | `API Reference` | Heading / nav label for the reference section. |
| `group` | `API Reference` | Sidebar group the reference pages are filed under. |
| `order` | `100` | Sort weight of the reference section relative to other groups. |
| `render_markdown_descriptions` | `true` | Render CommonMark in spec `description` fields rather than treating them as plain text. |

Your own Markdown always wins a slug collision, so you can override any
generated page by adding a Markdown file at the same slug.

## Generating a spec from your routes

Don't have a spec yet? The `laradocs:openapi` command scaffolds one by walking
your registered routes and reflecting your `FormRequest`s and API `Resource`s:

```bash
php artisan laradocs:openapi
```

By default it includes routes under the `api` prefix carrying the `api`
middleware, infers query/body parameters from each action's `FormRequest`
`rules()` (or a detectable inline `$request->validate([...])`), derives response
schemas from `JsonResource` / `ResourceCollection` return type-hints, and writes
the result to `docs/api/openapi.yaml`.

The output is a starting point, not a finished spec — descriptions and any
schema the inspectors could not infer are left for you to fill in. Useful
options:

```bash
# Overwrite an existing spec
php artisan laradocs:openapi --force

# Narrow the route surface and choose where to write
php artisan laradocs:openapi --prefix=api/v1 --middleware=auth:api --output=docs/api/v1.yaml
```

The defaults live under the `openapi.generator` config block:

| Key | Default | Purpose |
|---|---|---|
| `generator.prefix` | `api` | Only include routes whose URI starts with this prefix. |
| `generator.middleware` | `api` | Only include routes carrying this middleware name. |
| `generator.output` | `docs/api/openapi.yaml` | Where the generated spec is written (relative paths resolve from the project root). |
| `generator.server_url` | `null` | Base server URL recorded in the spec (falls back to `app.url`). |
| `generator.title` | `null` | The spec's `info.title` (falls back to `openapi.title`). |
| `generator.version` | `1.0.0` | The spec's `info.version`. |

## Re-publishing assets after an upgrade

The OpenAPI pages ship with dedicated styling (method badges, parameter tables,
the recursive schema tree, response blocks). These styles live in the package's
`resources/dist/laradocs.css`.

If you have **published** the assets — i.e. you previously ran
`php artisan vendor:publish --tag=laradocs-assets` — your local copy is a
snapshot frozen at publish time and will **not** include the OpenAPI styles (or
any other newer styles) after you upgrade Laradocs. Re-run the command with
`--force` to refresh it:

```bash
php artisan vendor:publish --tag=laradocs-assets --force
```

If you have never published the assets, you are always served the package's
bundled stylesheet and there is nothing to do.

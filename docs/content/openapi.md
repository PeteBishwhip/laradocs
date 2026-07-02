---
title: OpenAPI
description: Render a full, native, themeable API reference from an OpenAPI 3.0/3.1 specification — or scaffold the spec from your routes.
order: 6
---

# OpenAPI

Laradocs can render a complete API reference section straight from an OpenAPI
**3.0** or **3.1** specification that lives alongside your docs. Every operation
becomes its own navigable page — method and path, parameters, request and
response schemas (with `$ref`, `allOf`, `oneOf`, `anyOf`, `enum` and `nullable`
all expanded), copy-pasteable code samples in five languages, and a populated
table of contents.

The reference is rendered **natively** — no iframes, no client-side spec
fetching, no separate JavaScript bundle. Pages are server-rendered and flow
through the exact same sidebar, search, sitemap, SEO, theming and localisation
pipeline as your hand-written Markdown, so they look and behave like a
first-class part of your documentation.

> [!NOTE]
> This page documents both halves of the integration: **rendering** an existing
> spec (the read side) and **generating** a starter spec from your Laravel
> routes with `php artisan laradocs:openapi` (the write side). You can use
> either independently.

## At a glance

1. `composer require devizzent/cebe-php-openapi` — install the parser.
2. Set `LARADOCS_OPENAPI=true` (or `openapi.enabled` in the config).
3. Drop an `openapi.yaml` (or `.yml` / `.json`) into your docs directory.

That's it — the reference mounts under `/docs/api` and appears in the sidebar.
The rest of this page explains everything you can tune along the way.

---

## What the pages look like

### The overview page

The reference landing page (`/docs/api` by default) opens with an at-a-glance
**meta panel** — the base URL with a one-click copy button, and a version badge
pulled from the spec's `info.version`. Below it sits a compact, **collapsible
index**: one `<details>` block per resource (spec tag), collapsed by default, so
a spec with hundreds of endpoints stays scannable instead of unrolling every
operation at once.

Each row is a clickable endpoint — coloured method badge, path (with `{param}`
segments tinted) and summary — that links straight to the operation page. An
**Expand all / Collapse all** control toggles every resource at once, and
following a table-of-contents or `#hash` link automatically opens the resource
section it points at.

### Operation pages

Each operation gets its own page, built around:

- **An endpoint bar** — a solid, colour-coded method badge (GET, POST, PUT,
  PATCH, DELETE each get their own hue), the path with tinted `{param}`
  placeholders, and a copy button. Long paths stay on a single line and scroll
  horizontally rather than wrapping.
- **Parameters**, grouped by location (path / query / header / cookie) and
  presented as description lists, each showing name, type, format, and a
  `required` / `optional` marker.
- **Request and response bodies**, rendered as a clean, recursively nested
  property tree (see [How schemas render](#how-schemas-render) below).
- **Colour-coded response-status pills** — green for `2xx`, amber for `3xx`,
  red for `4xx` / `5xx`.
- **A description**, run through the site's Markdown pipeline (configurable —
  see [`render_markdown_descriptions`](#configuration)).

### How schemas render

Request and response schemas render as a nested property tree rather than a raw
JSON blob. The renderer resolves the spec ahead of time so the tree is always a
finite, themeable structure:

| Spec construct | How it renders |
|---|---|
| `$ref` | Inlined — the referenced schema's properties surface directly. |
| `allOf` | Merged into a single object. |
| `oneOf` / `anyOf` | A labelled, collapsible branch listing each variant. |
| `enum` | An **Allowed values** row of the permitted values. |
| `nullable: true` | A `nullable` marker beside the type. |
| `format` (e.g. `date-time`, `uuid`) | Shown next to the base type. |
| `deprecated: true` | A **Deprecated** pill on the operation or parameter. |
| Circular `$ref` | Guarded and marked as a **Circular reference** so rendering always terminates. |

Object and array branches are collapsible `<details>` elements with **Expand
all / Collapse all** toolbars. **Response** trees start collapsed to keep large
payloads from dominating the page; request bodies start expanded.

### Request &amp; response code samples

On wide screens, operation pages carry a **code-sample panel** in the right rail
(where the table of contents normally sits); on narrow screens it drops inline
near the top of the page. The panel shows copy-pasteable **request** snippets and
an example JSON **response** body.

Request snippets are generated in five languages, chosen from a dropdown:

| Language | Client shown |
|---|---|
| **cURL** | `curl` with headers and `-d` body |
| **PHP** | Laravel's `Http` facade |
| **JavaScript** | `fetch` |
| **Python** | `requests` |
| **Ruby** | `Net::HTTP` |

The URL, headers and an example request body are synthesised from the
operation's method, server URL and resolved request schema. Example values come
from each field's `type`, `format` and `enum` (for instance a `date-time` field
becomes `2024-01-01T00:00:00Z`, a `uuid` becomes a zeroed UUID, and an `enum`
uses its first value). Authentication is shown as a `Bearer YOUR_TOKEN`
placeholder for you to swap in.

Because the snippets flow through the same Markdown pipeline as the rest of the
site, they get **syntax highlighting** and **copy buttons** for free. The chosen
language **persists as you move between operation pages** — in `sessionStorage`
by default, or in a one-year cookie when cookie persistence is enabled (the same
`locale.cookie` consent flag the language selector uses). There is nothing to
configure: the panel appears automatically whenever an operation has a request or
response body to describe.

---

## Enabling the integration

The integration is **disabled by default**. Turn it on in two steps.

### 1. Install the parser

The spec is parsed by the optional
[`devizzent/cebe-php-openapi`](https://github.com/devizzent/cebe-php-openapi)
package, which is not pulled in automatically:

```bash
composer require devizzent/cebe-php-openapi
```

> [!NOTE]
> `devizzent/cebe-php-openapi` is a maintained fork of the original
> `cebe/php-openapi`. It is required for **both** rendering a spec and generating
> one with `laradocs:openapi`.

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

By default Laradocs searches each docs source for `openapi.yaml`, `openapi.yml`
or `openapi.json` (in that order) and mounts the **first** match under
`/docs/api`.

> [!NOTE]
> The spec is parsed once and **cached by path + modification time**, so edits
> are picked up automatically on the next request — no manual cache clear
> needed.

---

## URLs, tags and the sidebar

The loader emits one **overview** document plus one document **per operation**,
and files them like this:

- **Overview** → mounted at `base_slug` (e.g. `/docs/api`), labelled by the
  `title` config (default `Overview`).
- **Operations** → mounted at `base_slug/{tag}/{operation}`, where `{tag}` is a
  slug of the operation's **first** tag and `{operation}` is a slug of its
  **summary** — so the URL matches the page title, e.g.
  `/docs/api/background-processes/list-background-processes`. When an operation
  has no summary the segment falls back to its `operationId`, then to
  `method + path`. Collisions within a spec gain a stable numeric suffix
  (`…-2`, `…-3`). Operations with no tags are filed under a default group.

In the sidebar this becomes a single **API Reference** section (the `group`
config) containing the **Overview** page followed by one collapsible entry per
tag:

```
API REFERENCE
  Overview
  Orders          ▸
  Customers       ▸
  Payments        ▸
```

Tags are therefore how you organise the reference — group related operations
under the same tag and they'll share a sidebar section. The order within each
tag follows the spec's declaration order.

---

## Descriptions and Markdown

OpenAPI `description` fields (on the spec info, operations, parameters and
schemas) are run through the **same Markdown pipeline** as the rest of your
docs when `render_markdown_descriptions` is `true` (the default). That means
CommonMark, callouts, code fences and inline formatting all work inside your
spec descriptions, and they render with the site's styling.

Set the option to `false` to treat descriptions as plain, escaped text instead —
useful if your spec descriptions contain characters you don't want interpreted
as Markdown.

---

## Configuration

All options live under the `openapi` key of `config/laradocs.php`:

| Option | Default | Description |
|---|---|---|
| `enabled` | `false` | Master switch for the integration (env: `LARADOCS_OPENAPI`). |
| `files` | `['openapi.yaml', 'openapi.yml', 'openapi.json']` | Candidate spec filenames searched for in each docs source; the first match wins. |
| `base_slug` | `api` | URL segment the reference pages mount under (e.g. `/docs/api`). |
| `title` | `Overview` | Nav label and heading for the reference **landing** page (the operations index). It reads as a child of `group` — e.g. `API Reference › Overview` — rather than repeating the section name. Set to `null` to fall back to the spec's own `info.title`. |
| `group` | `API Reference` | Sidebar group heading the whole reference is filed under. |
| `order` | `100` | Sort weight of the reference section relative to other sidebar groups. |
| `render_markdown_descriptions` | `true` | Render CommonMark in spec `description` fields rather than treating them as plain text. |

A minimal custom configuration:

```php
// config/laradocs.php
'openapi' => [
    'enabled' => env('LARADOCS_OPENAPI', false),
    'base_slug' => 'reference',   // mount under /docs/reference
    'group' => 'HTTP API',        // sidebar heading
    'title' => 'Introduction',    // landing-page label
],
```

### Overriding a generated page with Markdown

Your own Markdown **always wins a slug collision**. To replace any generated
page — say, to hand-write a richer overview — add a Markdown file at the same
slug:

```
resources/docs/
└── api.md          ← overrides the generated /docs/api overview
```

The Markdown loader runs before the OpenAPI loader, so the file you author takes
precedence.

---

## Multi-version docs

When [multi-version docs](/docs/advanced/versioning) are enabled, drop a spec
into **each version directory** and the matching version's reference section is
rebuilt independently:

```
resources/docs/
├── v1/
│   └── openapi.yaml
└── v2/
    └── openapi.yaml
```

Each spec is parsed and cached per version, so `/docs/v1/api` and `/docs/v2/api`
stay in sync with their own spec files.

---

## Localised API reference

Just like content pages, an OpenAPI spec can be **translated per locale**. Drop a
localised spec alongside the default one using either the filename-suffix or the
locale-directory form:

```
resources/docs/
├── openapi.json          ← default locale
├── openapi.de.json       ← German (filename-suffix form)
└── fr/
    └── openapi.json      ← French (locale-directory form)
```

For a non-default locale, Laradocs prefers `openapi.{locale}.{ext}`, then
`{locale}/openapi.{ext}`, and falls back to the un-suffixed default spec when no
translation exists — so a partially translated site never 404s. Translate the
`summary` and `description` fields (and any other human-readable copy); keep the
`operationId`s, methods and paths identical across languages.

The un-suffixed `openapi.json` is optional: if you ship only locale-specific
specs (e.g. `openapi.en.json` and `openapi.de.json` with `locale.default` set to
`en`), the **default locale's** spec becomes the canonical one every language
mounts and links against.

> [!IMPORTANT]
> **Operation URLs are derived from the *default*-locale spec** — the un-suffixed
> `openapi.json`, or the default locale's own spec when no un-suffixed file
> exists. They stay identical across languages even when a summary is translated:
> a German operation whose summary reads "Alle Widgets auflisten" is still served
> at the English slug (`…/widgets/list-all-widgets`), which keeps deep links and
> the language switcher working across the whole reference.

---

> [!WARNING]
> **Directory name conflicts with `base_slug`.** The Markdown loader runs
> before the OpenAPI loader, so if your docs directory already contains a
> folder named `api/` (the default `base_slug`), that folder's content takes
> over the `/docs/api` URL and the OpenAPI overview is silently hidden — not an
> error. The symptom is that `/docs/api` shows your Markdown pages regardless
> of `LARADOCS_OPENAPI=true`.
>
> Fix it by either renaming the conflicting directory or changing `base_slug`
> to something that doesn't clash:
>
> ```php
> // config/laradocs.php
> 'openapi' => [
>     'base_slug' => 'api-reference',
> ],
> ```

## Generating a spec from your routes

Don't have a spec yet? The `laradocs:openapi` command scaffolds one by walking
your registered routes and reflecting your `FormRequest`s and API `Resource`s:

```bash
php artisan laradocs:openapi
```

By default it includes routes under the `api` prefix carrying the `api`
middleware, infers query/body parameters and response schemas, and writes the
result to `docs/api/openapi.yaml`.

> [!IMPORTANT]
> The output is a **starting point, not a finished spec.** Only what can be
> recovered by reflection is filled in; descriptions and any schema the
> inspectors can't infer are left as sensible defaults for you to refine. Treat
> the generated file as a scaffold you commit and then hand-edit.

### Command options

```bash
# Overwrite an existing spec
php artisan laradocs:openapi --force

# Narrow the route surface and choose where to write
php artisan laradocs:openapi \
    --prefix=api/v1 \
    --middleware=auth:api \
    --output=docs/api/v1.yaml
```

| Option | Falls back to | Purpose |
|---|---|---|
| `--output` | `openapi.generator.output` | Where the spec is written (relative paths resolve from the project root). |
| `--prefix` | `openapi.generator.prefix` | Only include routes whose URI starts with this prefix. Pass an empty string to disable the filter. |
| `--middleware` | `openapi.generator.middleware` | Only include routes carrying this middleware name. Pass an empty string to disable the filter. |
| `--force` | — | Overwrite the output file if it already exists (otherwise the command aborts). |

The `generator` config block holds the defaults and a couple of values with no
flag equivalent:

| Key | Default | Purpose |
|---|---|---|
| `generator.prefix` | `api` | Default URI-prefix filter. |
| `generator.middleware` | `api` | Default middleware filter. |
| `generator.output` | `docs/api/openapi.yaml` | Default output path. |
| `generator.server_url` | `null` | Base server URL recorded in the spec (falls back to `app.url`). |
| `generator.title` | `null` | The spec's `info.title` (falls back to `openapi.title`). |
| `generator.version` | `1.0.0` | The spec's `info.version`. |

### What gets inferred

**Routes → operations.** Every matching route becomes an operation. The
controller and action name derive a tag and a fallback `operationId`.

**Request bodies and query parameters** are inferred from the action, in order
of reliability:

1. A type-hinted `FormRequest` parameter — its `rules()` array is the
   authoritative input contract.
2. A detectable inline `$request->validate([...])` (or `$this->validate(...)`)
   call in the action body, scraped from the method source as a fallback.

Each field's ruleset is mapped to JSON Schema:

| Validation rule | JSON Schema |
|---|---|
| `string` | `type: string` |
| `integer` / `int` | `type: integer` |
| `numeric` | `type: number` |
| `boolean` / `bool` | `type: boolean` |
| `array` | `type: array` |
| `email` | `type: string`, `format: email` |
| `url` | `type: string`, `format: uri` |
| `uuid` | `type: string`, `format: uuid` |
| `ulid` | `type: string` |
| `date` | `type: string`, `format: date-time` |
| `in:a,b,c` | `enum: [a, b, c]` |
| `required` | field added to the object's `required` list |
| `nullable` | `nullable: true` |
| `min:n` / `max:n` | `minimum`/`maximum` (numbers), `minItems`/`maxItems` (arrays), or `minLength`/`maxLength` (strings) |
| anything else | falls back to `type: string` |

**Response schemas** are inferred from the action's return type-hint. When it
returns a `JsonResource` (or a `ResourceCollection`), the resource's `toArray()`
is scraped for its top-level keys to build an object schema; a collection wraps
that object in an `array`. The success status defaults to `200`, or `201` for
routes that only answer `POST`.

> [!NOTE]
> Response inference is intentionally conservative — only field **names** are
> recoverable from `toArray()` source, so every response property defaults to
> `type: string` for you to refine.

### Refining with the `#[ApiOperation]` attribute

Inference can't recover everything — human-readable summaries, stable
`operationId`s, tags, or a deprecation flag. Add the `#[ApiOperation]` attribute
to a controller action to override exactly those values:

```php
use Laradocs\OpenApi\Generator\Attributes\ApiOperation;

#[ApiOperation(
    summary: 'List orders',
    description: 'Returns a paginated list of the current team\'s orders.',
    operationId: 'orders.index',
    tags: ['Orders'],
    deprecated: false,
)]
public function index(): OrderResourceCollection
{
    // ...
}
```

Every argument is optional — only the ones you provide override the inferred
value. `tags` **replaces** the inferred controller-derived tag, which is how you
control the sidebar grouping of a generated reference.

### A typical workflow

1. Run `php artisan laradocs:openapi` to scaffold `docs/api/openapi.yaml`.
2. Commit it, then hand-edit: flesh out descriptions, tighten response property
   types, add examples.
3. Sprinkle `#[ApiOperation]` attributes on actions for summaries/tags you want
   to keep in code and regenerate from.
4. Re-run with `--force` when routes change; re-apply your manual edits (or keep
   them isolated so a regenerate is a clean diff).

---

## Customising the look

The OpenAPI pages ship with dedicated styling (method badges, parameter lists,
the recursive schema tree, response blocks, the code-sample panel). Everything is
built on the same CSS custom properties (`--dc-accent`, `--dc-fg`, `--dc-muted`,
…) as the rest of the theme, so it follows your accent colour and light/dark mode
automatically.

### Overriding the markup

Publish the views and edit the partials under
`resources/views/vendor/laradocs/partials/openapi/`:

```bash
php artisan vendor:publish --tag=laradocs-views
```

| Partial | Renders |
|---|---|
| `overview.blade.php` | The reference landing page (meta panel + resource index). |
| `operation.blade.php` | A single operation page (endpoint bar, params, bodies, samples). |
| `parameters.blade.php` | The parameters section. |
| `response.blade.php` | A single response block. |
| `schema.blade.php` | The recursive schema/property tree. |
| `property-head.blade.php` | One property row's name + type + required marker. |
| `path.blade.php` | An endpoint path with tinted `{param}` segments. |
| `schema-toolbar.blade.php` | The Expand all / Collapse all controls. |

### Overriding the styling

The styles live in the package's bundled `resources/dist/laradocs.css` under the
`.laradocs-openapi*` selectors. You can layer your own CSS on top, or publish and
edit the stylesheet directly (see the next section).

---

## Re-publishing assets after an upgrade

If you have **published** the assets — i.e. you previously ran
`php artisan vendor:publish --tag=laradocs-assets` — your local copy is a
snapshot frozen at publish time and will **not** include newer styles (such as
the OpenAPI styling, or later refinements) after you upgrade Laradocs. Re-run the
command with `--force` to refresh it:

```bash
php artisan vendor:publish --tag=laradocs-assets --force
```

If you have never published the assets, you are always served the package's
bundled stylesheet and there is nothing to do.

---

## Troubleshooting

### The reference doesn't appear at `/docs/api`

- Confirm `openapi.enabled` is `true` (or `LARADOCS_OPENAPI=true`).
- Confirm `devizzent/cebe-php-openapi` is installed.
- Confirm a spec file matching `openapi.files` exists in the docs source.

### A directory named `api/` shadows the overview

> [!WARNING]
> **Directory name conflicts with `base_slug`.** The Markdown loader runs
> before the OpenAPI loader, so if your docs directory already contains a folder
> named `api/` (the default `base_slug`), that folder's content takes over the
> `/docs/api` URL and the OpenAPI overview is silently hidden — not an error.
> The symptom is that `/docs/api` shows your Markdown pages regardless of
> `LARADOCS_OPENAPI=true`.
>
> Fix it by renaming the conflicting directory, or by changing `base_slug` to
> something that doesn't clash:
>
> ```php
> // config/laradocs.php
> 'openapi' => [
>     'base_slug' => 'api-reference',
> ],
> ```

### `laradocs:openapi` reports "No API routes matched"

The `--prefix` / `--middleware` filters excluded every route. Loosen them — e.g.
`--prefix=` (empty) to drop the prefix filter, or point `--middleware` at the
guard your API routes actually use (`auth:sanctum`, `auth:api`, …).

### Styling looks unstyled after an upgrade

You've published the assets and they're frozen at an older version — re-run
`php artisan vendor:publish --tag=laradocs-assets --force` (see
[above](#re-publishing-assets-after-an-upgrade)).

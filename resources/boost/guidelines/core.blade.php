# Laradocs

Laradocs (`petebishwhip/laradocs`) turns a folder of markdown files into a
documentation site served from this application. Pages are `.md` files with YAML
front-matter under the configured docs path (`laradocs.docs.path`, default
`docs/`), served at `/docs`.

Use the **`laradocs-development` skill** when authoring documentation or working
on Laradocs configuration. It covers the full front-matter reference, Artisan
commands, rich-content syntax, variables and macros, the facade API, publishing,
SEO, search and feeds.

The rules below apply whenever you touch this application, skill or no skill:

- Create a page with `php artisan make:doc {name}` rather than writing the file
  by hand — it produces correct front-matter. `name` is the doc path, e.g.
  `guide/getting-started`.
- Every page needs a `title` in its front-matter; `php artisan docs:lint`
  enforces it. Front-matter keys are snake_case (`updated_at`, `search_rank`).
- Docs files live under the configured docs path (`laradocs.docs.path`, default
  `base_path('docs')`) and use the `.md` or `.markdown` extension.
- Nested folders become nested navigation sections, and directory depth maps to
  URL depth: `docs/guide/routing.md` is served at `/docs/guide/routing`.
- `_index.md` is a section landing page: `docs/guide/_index.md` →
  `/docs/guide`, and the root `docs/_index.md` → `/docs` (the docs home). The
  index filename is configurable via `laradocs.docs.index` (default `_index`).
- **Never put closures in `config/laradocs.php`** — they break `config:cache`.
  Register dynamic variables and macros through the `Laradocs` facade in a
  service provider's `boot()` method instead.
- Customise the UI by publishing what you need
  (`php artisan vendor:publish --tag=laradocs-views`), never by editing files
  under `vendor/`.
- Run `php artisan laradocs:clear` after changing config, macros or variables,
  and `php artisan docs:lint` before committing.

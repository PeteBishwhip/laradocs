# Contributing

Contributions are welcome and will be fully credited.

## Pull requests

- **Tests are required.** This package maintains 100% test coverage; add Pest
  tests for any new behaviour.
- **Static analysis must pass** at PHPStan's and Psalm's maximum level.
- **Code style** is enforced with Laravel Pint.

Run the full quality suite before opening a PR:

```bash
composer test            # pint --test + phpstan + psalm + pest
composer test:coverage   # enforces 100% coverage
```

Psalm is baselined (`psalm-baseline.xml`) against pre-existing debt outside the
frozen API surface described below. Don't add new entries to that baseline —
if a change to one of the frozen classes needs a suppression, justify it
inline with a comment instead.

## Setup

```bash
git clone https://github.com/petebishwhip/laradocs
cd laradocs
composer install
composer serve   # preview the bundled docs site
```

## Versioning

This project follows [Semantic Versioning](https://semver.org). Breaking changes
land in major releases only. Supported Laravel versions: 11, 12 and 13. Supported
PHP versions: 8.3, 8.4 and 8.5.

## Public API surface

The following make up laradocs' frozen public API — the surface SemVer
compatibility promises apply to:

- `Laradocs\Laradocs` (also reachable via the `Laradocs` facade) — the
  package's stateful entry point.
- Value objects: `Laradocs\Documents\Document`, `Laradocs\Documents\DocumentTree`,
  `Laradocs\Documents\DocumentCollection`, `Laradocs\Documents\Tag`,
  `Laradocs\Documents\TreeNode`, `Laradocs\Metadata\Metadata`.
- Extension points under `Laradocs\Contracts\*` (`DocumentLoader`,
  `DocumentParser`, `DocumentContentRenderer`, `MetadataResolver`,
  `HtmlExtension`, `MarkdownExtension`, `OgImageGenerator`,
  `OpenApiSpecGenerator`).

Everything else under `src/` (console commands, HTTP controllers, view
composers, internal builders) is implementation detail and may change in a
minor release.

**Value objects are immutable.** `Document`, `DocumentTree`, `TreeNode`, `Tag`
and `Metadata` are `final`, construct-once, and annotated `@psalm-immutable`:
every property is `readonly` and every method is side-effect free, returning
a new instance (e.g. `Document::withHtml()`) rather than mutating in place.
`DocumentCollection` extends Laravel's `Collection` and therefore remains
mutable like any collection, but its own domain methods (`visible()`,
`ordered()`, `byGroup()`, `byTag()`, `findBySlug()`) never mutate the
collection they're called on.

**What counts as breaking (major-only):**

- Removing or renaming a public class, interface, method, or constructor
  parameter.
- Narrowing a parameter type, widening a return type's nullability the wrong
  way (`?string` → `string` is fine; `string` → `?string` is not, since
  callers may not null-check), or otherwise tightening a contract a consumer
  already relies on.
- Adding a new abstract method to a `Contracts\*` interface — this breaks
  every existing implementation and is treated as breaking even though PHP
  doesn't require a major version bump to compile it.
- Changing the behaviour of an existing method in a way current tests don't
  already pin down.

**What's safe in a minor release:**

- Adding a new optional constructor parameter with a default value.
- Adding a new public method.
- Widening a parameter's accepted types (e.g. `string` → `string|Stringable`).
- Adding a new interface to `Contracts\*` for a new extension point.

## Deprecation policy

When a public API needs to change in a breaking way:

1. Introduce the replacement alongside the old API in a minor release.
2. Mark the old member `@deprecated`, stating the replacement and the major
   version it will be removed in, and note it in `CHANGELOG.md`.
3. Keep the deprecated member fully functional for at least one minor
   release cycle.
4. Remove it only in the next major release.

Deprecations must not fail PHPStan/Psalm or emit runtime warnings/errors —
`@deprecated` is a docblock-level signal for IDEs and static analysis
consumers, not a runtime `trigger_error()`.

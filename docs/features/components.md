---
title: Components
description: Drop Blade-style components straight into your markdown.
order: 4
---

# Components

Components let you write rich, reusable blocks with familiar
Blade-component syntax — right inside your markdown:

```markdown
<x-callout type="warning" title="Heads up">
Back up your database before running migrations.
</x-callout>
```

<x-callout type="warning" title="Heads up">
Back up your database before running migrations.
</x-callout>

Under the hood a component is just a friendlier face on the
[macro engine](/docs/features/macros): `<x-name>` resolves to the macro
registered under `name`. The two syntaxes round-trip, so this is exactly
equivalent to the call above:

```markdown
@docs('callout', type: 'warning', title: 'Heads up', slot: 'Back up your database before running migrations.')
```

## Whitelist-driven

There is **no arbitrary Blade execution**. A `<x-...>` tag only renders
when its name is a registered macro — the macro registry _is_ the
whitelist. Attribute values are always treated as literal data and are
never evaluated as PHP, so authors cannot smuggle expressions into a page.

Anything that isn't whitelisted is left in the document untouched, so a
typo'd or unknown component is obvious rather than silently dropped.

The package registers a `callout` component out of the box. Register your
own exactly as you would a macro — see
[Registering your own](/docs/features/macros#registering-your-own):

```php
use Laradocs\Facades\Laradocs;

Laradocs::macro('callout', 'partials.callout'); // now <x-callout> works
```

## Attributes

| Syntax | Example | Becomes |
|---|---|---|
| String | `type="warning"` | `'warning'` (string) |
| Bare scalar | `count=3`, `open=true` | `3` (int), `true` (bool) |
| Boolean (valueless) | `dismissible` | `true` |
| Bound | `:count="3"`, `:open="true"` | `3` (int), `true` (bool) |

Quoted values are always strings (`active="true"` is the string
`"true"`); bare and `:bound` values are coerced just like macro arguments
— `true`/`false` become booleans and numbers become numbers. Bound
attributes (`:name`) are a convenience for those coercions only; the
expression is **never** evaluated as PHP.

## Slots

The content between an opening and closing tag is passed to the macro as a
`slot` argument:

```markdown
<x-callout>This text becomes the **slot**.</x-callout>
```

The slot is handed over verbatim — inline HTML and already-expanded
macros survive, but block-level markdown inside a slot is not
re-processed. For prose-heavy callouts, reach for a
[GitHub-style alert blockquote](/docs/features/rich-content) instead.

Self-closing tags carry no slot:

```markdown
<x-badge text="Beta" />
```

## Showing a component literally

To document a component without rendering it, use any of these escapes:

- **Backslash** the opening bracket — `\<x-callout>` renders the literal
  text `<x-callout>`.
- Wrap it in an inline code span — `` `<x-callout>` ``.
- Put it in a fenced code block (as in the examples above).

Components inside code spans and fenced blocks are always left alone, so
you can document the syntax freely.

## Disabling

Turn the feature off entirely in `config/laradocs.php`:

```php
'parser' => [
    'extensions' => [
        'components' => false,
    ],
],
```

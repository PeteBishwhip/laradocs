---
title: Localisation
description: Translate the documentation interface and offer readers a language selector.
---

# Localisation

Laradocs localises on two independent layers:

1. **Interface strings** — navigation labels, the search palette, the table of
   contents, the empty state and so on. Every one is rendered through Laravel's
   translation helper, so you can translate the entire chrome and ship several
   languages side by side.
2. **Page content** — your actual markdown pages. Drop a translated copy
   alongside the original and Laradocs serves it for the matching locale, with
   automatic fallback when a translation is missing.

A built-in language selector in the header lets readers switch between the
locales you offer; the choice drives both layers at once.

## Translating content

Translate a page by adding a locale-tagged copy of its markdown file. Two
conventions are recognised — use whichever you prefer (you can even mix them):

| Convention            | English original          | French translation              |
| --------------------- | ------------------------- | ------------------------------- |
| **Filename suffix**   | `guide/intro.md`          | `guide/intro.fr.md`             |
| **Locale directory**  | `guide/intro.md`          | `fr/guide/intro.md`             |

Both forms resolve to the **same slug** (`guide/intro`), so the public URL
(`/docs/guide/intro`) is identical in every language — switching locale swaps
the content under a stable address rather than sending the reader to a
different path. Section index files work too: `_index.fr.md` or
`fr/_index.md` translates the landing page.

### Which locales are recognised

A `.fr` suffix or `fr/` directory is only treated as a translation when `fr`
is one of your **available locales** — the same list that powers the language
selector (see [Choosing the available locales](#overriding-auto-detection)).
This is what keeps an ordinary file such as `release-2.0.md` or a content
directory that merely happens to share a locale's name from being mistaken for
a translation.

In practice this means: publish the language files, add a locale (e.g. copy
`en` to `fr`, or list it in `locale.available`), and your `*.fr.md` /
`fr/*.md` pages light up automatically.

### Fallback rules

For each page, the document served on a given request is resolved in this
order:

1. **The exact translation** for the request's locale, when it exists
   (`intro.fr.md` for a French reader).
2. **The default-locale page** otherwise — the un-suffixed file (e.g.
   `intro.md`), which belongs to `locale.default`. This is the fallback for any
   page a reader's language hasn't translated yet, so a partially translated
   site never 404s: untranslated pages simply appear in the default language.
3. **Any remaining variant**, as a last resort, so a page that exists *only* in
   some non-default locale is still reachable rather than hidden outright.

Because the fallback is per page, you can translate your documentation
incrementally — start with the pages that matter most and the rest keep
rendering in the default language until you get to them.

> Keep a translation's slug aligned with its original. If you set an explicit
> `slug:` in front-matter, use the same value in every language (or omit it and
> let the shared filename drive the slug) so the translations collapse onto one
> URL.

Content translation composes with [multi-version docs](/docs/guide/versioning)
— place locale files inside each version directory (e.g.
`v2/guide/intro.fr.md`).

## Publishing the language files

The English strings ship inside the package. Publish them to your application
to translate or override them:

```bash
php artisan vendor:publish --tag=laradocs-lang
```

This copies the files to `lang/vendor/laradocs/en/laradocs.php`. Edit that file
to reword the English interface, or copy the `en` directory to a new locale
code to translate it:

```bash
cp -r lang/vendor/laradocs/en lang/vendor/laradocs/fr
```

Then translate the values in `lang/vendor/laradocs/fr/laradocs.php`:

```php
return [
    'nav' => [
        'home' => 'Accueil',
        'previous' => 'Précédent',
        'next' => 'Suivant',
        // …
    ],
    // …
];
```

You don't have to translate every key — anything you leave out falls back to
the package's default string automatically.

## Choosing the default language

Which language the docs render in is resolved on every request. The default is
`en`. Override it with the `locale.default` option (or the `LARADOCS_LOCALE`
environment variable):

```php
// config/laradocs.php
'locale' => [
    'default' => env('LARADOCS_LOCALE', 'en'),
],
```

The full resolution order is implemented by `Laradocs\Support\Locale::fallback()`:

1. The explicit `laradocs.locale.default` value.
2. The application locale, when a matching `available` entry exists.
3. The first locale listed in `available`.

The locale actually used for a given request is decided by
`Laradocs\Support\Locale::determine()`, which lets a visitor's explicit choice
win over the default (see below) before falling back to it.

## The language selector

Once you have at least two locale directories under `lang/vendor/laradocs/`,
the package detects them automatically and shows a language selector in the
header. No configuration is required — just publish the lang files, copy and
translate a new directory, and the selector appears.

```bash
# English is already published; add French:
cp -r lang/vendor/laradocs/en lang/vendor/laradocs/fr
# Translate lang/vendor/laradocs/fr/laradocs.php …
```

The selector is hidden automatically when fewer than two locales are detected.
Disable it entirely with `'selector' => false` (or `LARADOCS_LOCALE_SELECTOR=false`).

### Locale labels

By default the locale code itself is used as the label in the selector (e.g.
`fr`). Add a `meta.php` file inside the locale directory to provide a
human-readable name:

```php
// lang/vendor/laradocs/fr/meta.php
return [
    'label' => 'Français',
];
```

### Overriding auto-detection

If you prefer to control the locale list explicitly — for example to hide a
locale from the selector without removing its files — set `locale.available` to
an array. Any array value takes precedence over the filesystem scan:

```php
'locale' => [
    'available' => [
        'en' => 'English',
        'fr' => 'Français',
        'de' => 'Deutsch',
    ],
],
```

Leave it `null` (the default) to use auto-detection. Set it to an empty array
(`[]`) to opt out entirely and hide the selector regardless of what is on disk.

### How switching works

Selecting a language appends a `?lang=<code>` query parameter; the package
validates it against the detected locales, applies it for the request, and
stores the choice in a `laradocs_locale` cookie so it persists as the reader
navigates. Unknown codes are ignored, so the query string can never force the
UI into a locale that has no translation directory.

The locale is applied per request and the previous locale is restored once the
response has rendered, so the package is safe to run under
[Laravel Octane](https://laravel.com/docs/octane) — one request's language is
never carried into the next.

## Overriding individual strings

If you only want to tweak a phrase or two, publish the language files and edit
the relevant key. The keys are grouped by area — `nav`, `search`, `toc`,
`page`, `empty`, `macros`, `theme` and `language`. For example, to reword the
"Edit this page" link without touching anything else:

```php
// lang/vendor/laradocs/en/laradocs.php
'page' => [
    'edit' => 'Suggest an edit',
],
```

To reword the link label, publish the language files and update `page.edit`.

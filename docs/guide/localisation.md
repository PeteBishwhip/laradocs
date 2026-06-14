---
title: Localisation
description: Translate the documentation interface and offer readers a language selector.
---

# Localisation

Every user-facing string in the bundled views — navigation labels, the search
palette, the table of contents, the empty state and more — is rendered through
Laravel's translation helper. That means you can translate the entire
documentation interface, ship multiple languages side by side, and let readers
switch between them with the built-in language selector.

> The strings being translated are the **interface** chrome. Your actual
> documentation content lives in your markdown files; translate those by
> creating per-language pages or directories.

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

The full resolution order is implemented by
`LaradocsServiceProvider::defaultLocale()`:

1. The explicit `laradocs.locale.default` value.
2. The application locale, when a matching `available` entry exists.
3. The first locale listed in `available`.

The locale actually used for a given request is decided by
`LaradocsServiceProvider::determineLocale()`, which lets a visitor's explicit
choice win over the default (see below) before falling back to it.

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

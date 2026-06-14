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
the package's English string automatically.

## Choosing the default language

Which language the docs render in is resolved on every request. Set the
default with the `locale.default` option (or the `LARADOCS_LOCALE` environment
variable):

```php
// config/laradocs.php
'locale' => [
    'default' => env('LARADOCS_LOCALE'), // null = use the app's locale
],
```

When `default` is `null`, Laradocs uses your application's own locale
(`config('app.locale')`). The full resolution order is implemented by
`LaradocsServiceProvider::defaultLocale()`:

1. The explicit `laradocs.locale.default` value, if set.
2. The application locale, when a matching `available` entry exists.
3. The first locale listed in `available`.

The locale actually used for a given request is decided by
`LaradocsServiceProvider::determineLocale()`, which lets a visitor's explicit
choice win over the default (see below) before falling back to it.

## The language selector

List the languages you want to offer in `locale.available`. Keys are locale
codes (matching a published translation directory); values are the labels shown
in the selector:

```php
'locale' => [
    'default' => env('LARADOCS_LOCALE'),
    'available' => [
        'en' => 'English',
        'fr' => 'Français',
        'de' => 'Deutsch',
    ],
    'selector' => true,
],
```

A language selector then appears in the header. It is hidden automatically when
fewer than two languages are configured, and you can disable it entirely with
`'selector' => false` (or `LARADOCS_LOCALE_SELECTOR=false`).

Selecting a language appends a `?lang=<code>` query parameter; the package
validates it against `available`, applies it for the request, and stores the
choice in a `laradocs_locale` cookie so it persists as the reader navigates.
Unknown codes are ignored, so the query string can never force the UI into a
locale you haven't configured.

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

The edit link also honours the `ui.edit.label` config option; setting that
overrides the translated string for every locale.

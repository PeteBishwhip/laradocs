<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Master Switch
    |--------------------------------------------------------------------------
    |
    | When disabled, no routes are registered and the docs are completely
    | inaccessible. Handy for hiding docs in production environments.
    |
    */

    'enabled' => env('LARADOCS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Routing
    |--------------------------------------------------------------------------
    |
    | "prefix"     The URL segment your docs live under (e.g. /docs).
    | "domain"     Optionally serve docs on a dedicated subdomain.
    | "middleware" Middleware applied to every docs route.
    | "name"       Route name prefix, used by route('laradocs.show', ...).
    |
    */

    'route' => [
        'prefix' => env('LARADOCS_ROUTE_PREFIX', 'docs'),
        'domain' => env('LARADOCS_ROUTE_DOMAIN'),
        'middleware' => ['web'],
        'name' => 'laradocs.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Documentation Source
    |--------------------------------------------------------------------------
    |
    | "path"             Absolute path to the directory holding your markdown.
    | "extensions"       File extensions treated as documents.
    | "ignored_patterns" fnmatch() patterns for files/dirs to skip.
    | "index"            Filename treated as a section landing page.
    |
    */

    'docs' => [
        'path' => env('LARADOCS_PATH', base_path('docs')),
        'extensions' => ['md', 'markdown'],
        'ignored_patterns' => ['.*', '_drafts', 'README.md'],
        'index' => '_index',
    ],

    /*
    |--------------------------------------------------------------------------
    | Slug / Route Generation Strategy
    |--------------------------------------------------------------------------
    |
    | "strategy" One of: filename | metadata | both.
    |   filename — slugs are derived purely from the file path.
    |   metadata — slugs come from front-matter "slug:" (falls back to filename).
    |   both     — metadata wins when present, otherwise filename.
    |
    */

    'routing' => [
        'strategy' => env('LARADOCS_ROUTING_STRATEGY', 'both'),
        'fallback' => 'filename',
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-file Metadata Defaults
    |--------------------------------------------------------------------------
    |
    | Applied to every document unless overridden by front-matter.
    |
    */

    'metadata' => [
        'default' => [
            'order' => 999,
            'hidden' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Markdown Parser
    |--------------------------------------------------------------------------
    |
    | "extensions"  Which built-in feature sets to enable.
    | "highlighter" Code highlighting engine (null disables it).
    | "toc"         Min/max heading levels collected for the on-page TOC.
    |
    */

    'parser' => [
        'extensions' => [
            'gfm' => true,
            'attributes' => true,
            'footnotes' => true,
            'callouts' => true,
            'heading_anchors' => true,
            'images' => true,
            'video' => true,
            'variables' => true,
            'macros' => true,
        ],
        'highlighter' => env('LARADOCS_HIGHLIGHTER', 'shiki-css'),
        'unknown_variable' => 'blank', // blank | raw
        'toc' => [
            'min_level' => 2,
            'max_level' => 3,
            'min_headings' => 2,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Interface
    |--------------------------------------------------------------------------
    |
    | "theme"   Colour mode: auto | light | dark.
    | "preset"  Visual preset shipped with the package:
    |             classic — sidebar + centered article + right TOC.
    |             minimal — distraction-free single column, slimline header.
    |             wide    — app-style, fills the viewport, denser nav.
    | "accent"  Any CSS colour. Drives links, active nav, focus rings.
    | "fonts"   Override the built-in stacks (leave null to keep defaults).
    |
    */

    'ui' => [
        'theme' => env('LARADOCS_THEME', 'auto'),
        'preset' => env('LARADOCS_UI_PRESET', 'classic'),
        'accent' => env('LARADOCS_ACCENT', '#2563eb'),
        'fonts' => [
            'sans' => env('LARADOCS_FONT_SANS'),
            'mono' => env('LARADOCS_FONT_MONO'),
        ],
        'brand' => [
            'title' => env('LARADOCS_TITLE', 'Documentation'),
            'tagline' => env('LARADOCS_TAGLINE'),
            'logo' => env('LARADOCS_LOGO'),
            'favicon' => env('LARADOCS_FAVICON'),
        ],

        /*
        | Header navigation. Each link is an associative array:
        |   ['label' => 'GitHub', 'url' => 'https://...', 'external' => true]
        | Quick wins via ENV:
        |   LARADOCS_GITHUB_URL — adds a "GitHub" link to the header.
        */
        'header' => [
            'links' => array_values(array_filter([
                env('LARADOCS_GITHUB_URL') ? [
                    'label' => 'GitHub',
                    'url' => env('LARADOCS_GITHUB_URL'),
                    'external' => true,
                ] : null,
            ])),
        ],

        /*
        | Sidebar behaviour.
        */
        'sidebar' => [
            'collapsible' => true,
            'show_root' => true,
        ],

        /*
        | Footer. Set 'enabled' => false to hide entirely.
        */
        'footer' => [
            'enabled' => (bool) env('LARADOCS_FOOTER', true),
            'text' => env('LARADOCS_FOOTER_TEXT'),
            'links' => [
                // ['label' => 'Privacy', 'url' => '/privacy'],
            ],
        ],

        /*
        | Edit-this-page link rendered on every document. The url template
        | accepts the {path} placeholder which expands to the document slug.
        |   LARADOCS_EDIT_URL=https://github.com/me/app/edit/main/docs/{path}.md
        */
        'edit' => [
            'url' => env('LARADOCS_EDIT_URL'),
            'label' => env('LARADOCS_EDIT_LABEL', 'Edit this page'),
        ],

        'search' => [
            'enabled' => (bool) env('LARADOCS_SEARCH', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Rendered HTML and the document tree are cached keyed by file mtime, so
    | edits are picked up automatically. Disable to always render fresh.
    |
    */

    'cache' => [
        'enabled' => env('LARADOCS_CACHE', true),
        'store' => env('LARADOCS_CACHE_STORE'), // null = default store
        'ttl' => env('LARADOCS_CACHE_TTL', 86400),
        'key_prefix' => 'laradocs',
    ],

    /*
    |--------------------------------------------------------------------------
    | Variables
    |--------------------------------------------------------------------------
    |
    | Static key => value pairs interpolated into docs via {{ key }}.
    | Dynamic values may be registered from a service provider with
    | Laradocs::variables(fn () => [...]). No closures here (config:cache).
    |
    */

    'variables' => [
        // 'app_name' => 'My Application',
    ],

    /*
    |--------------------------------------------------------------------------
    | Macros
    |--------------------------------------------------------------------------
    |
    | Reusable named blocks invoked via @docs('name', ...args). Values are
    | Blade view names. Register closures from a provider instead if needed.
    |
    */

    'macros' => [
        // 'alert' => 'laradocs::macros.alert',
    ],

];

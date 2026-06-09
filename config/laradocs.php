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
    | "register"   When false, the package skips registering its own routes so
    |              the consumer app can wire the render action into a route it
    |              owns (e.g. behind tenant-resolving middleware). The package's
    |              controllers are still available; see DocumentRouter for the
    |              canonical action references.
    | "prefix"     The URL segment your docs live under (e.g. /docs).
    | "domain"     Optionally serve docs on a dedicated subdomain.
    | "middleware" Middleware applied to every docs route.
    | "name"       Route name prefix, used by route('laradocs.show', ...).
    |
    */

    'route' => [
        'register' => env('LARADOCS_ROUTE_REGISTER', true),
        'prefix' => env('LARADOCS_ROUTE_PREFIX', 'docs'),
        'domain' => env('LARADOCS_ROUTE_DOMAIN'),
        'middleware' => ['web'],
        'name' => env('LARADOCS_ROUTE_NAME', 'laradocs.'),
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
        'accent' => env('LARADOCS_ACCENT', '#FF2D20'),
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
        | accepts the following placeholders:
        |   {file} — relative path on disk including extension (recommended)
        |   {path} — same as {file} with the .md / .markdown extension stripped
        |   {ext}  — just the extension, e.g. "md"
        |
        |   LARADOCS_EDIT_URL=https://github.com/me/app/edit/main/docs/{file}
        */
        'edit' => [
            'url' => env('LARADOCS_EDIT_URL'),
            'label' => env('LARADOCS_EDIT_LABEL', 'Edit this page'),
        ],

        'search' => [
            'enabled' => (bool) env('LARADOCS_SEARCH', true),
        ],

        /*
        | Global banner displayed above the header on every page.
        | "type" accepts: info | alert | danger
        | "message" may contain HTML (e.g. a link for a CTA).
        |
        |   LARADOCS_BANNER=1
        |   LARADOCS_BANNER_TYPE=info
        |   LARADOCS_BANNER_MESSAGE="<a href='/changelog'>v2 is out</a> — see what's new."
        */
        'banner' => [
            'enabled' => (bool) env('LARADOCS_BANNER', false),
            'type' => env('LARADOCS_BANNER_TYPE', 'info'),
            'message' => env('LARADOCS_BANNER_MESSAGE'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics
    |--------------------------------------------------------------------------
    |
    | Drop-in analytics integrations. Each provider is opt-in: set the site
    | identifier and a small script tag is injected into every docs page.
    |
    | "fathom.site"      Your Fathom site ID (e.g. "ABCDEFGH").
    | "fathom.script"    Override the script URL (defaults to cdn.usefathom.com).
    | "fathom.spa"       SPA mode: "auto", "history", "hash" — see Fathom docs.
    | "google.measurement_id" GA4 measurement ID (e.g. "G-XXXXXXXXXX").
    | "google.anonymize_ip"   Anonymise visitor IPs (recommended in the EU).
    |
    */

    'analytics' => [
        'fathom' => [
            'site' => env('LARADOCS_FATHOM_SITE'),
            'script' => env('LARADOCS_FATHOM_SCRIPT', 'https://cdn.usefathom.com/script.js'),
            'spa' => env('LARADOCS_FATHOM_SPA'),
        ],
        'google' => [
            'measurement_id' => env('LARADOCS_GA_MEASUREMENT_ID'),
            'anonymize_ip' => (bool) env('LARADOCS_GA_ANONYMIZE_IP', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SEO & Social Meta
    |--------------------------------------------------------------------------
    |
    | Every page is served with rich SEO meta tags generated by
    | ralphjsmit/laravel-seo: a <title>, meta description, Open Graph and
    | Twitter cards, a canonical URL, and JSON-LD structured data. Sensible
    | values are derived automatically from each page's content and
    | front-matter — the settings below tune the site-wide defaults.
    |
    | Per-page overrides live in front-matter, either via the top-level
    | "title", "description", "image", "author" and "tags" keys or a dedicated
    | "seo:" block for finer control (robots, canonical, type, …). See the
    | "SEO" guide for the full reference.
    |
    */

    'seo' => [
        'enabled' => (bool) env('LARADOCS_SEO', true),

        // Brand name used in <title> suffixes and og:site_name.
        // Falls back to ui.brand.title when left null.
        'site_name' => env('LARADOCS_SEO_SITE_NAME'),

        // Appended to every page <title>, e.g. " · Acme Docs". When null a
        // suffix is built from the site name; set to an empty string to
        // disable the suffix entirely.
        'title_suffix' => env('LARADOCS_SEO_TITLE_SUFFIX'),

        // Fallback meta description, used when a page declares none and one
        // cannot be derived from its content. Falls back to ui.brand.tagline.
        'description' => env('LARADOCS_SEO_DESCRIPTION'),

        // Derive a description from the opening paragraph when a page omits
        // one. Disable to only ever use explicit descriptions / the fallback.
        'auto_description' => (bool) env('LARADOCS_SEO_AUTO_DESCRIPTION', true),

        // Default social / Open Graph image (absolute URL, or a path that
        // resolves under your public directory).
        'image' => env('LARADOCS_SEO_IMAGE'),

        // Default author attribution for article meta + schema.
        'author' => env('LARADOCS_SEO_AUTHOR'),

        // X (formerly Twitter) handle (without the @) for twitter:site / creator tags.
        'x' => env('LARADOCS_SEO_X'),

        // X card type. Accepts: summary_large_image | summary | app | player.
        // summary_large_image is best for pages with a dedicated cover image;
        // summary renders a small thumbnail and suits text-heavy reference pages.
        'x_card' => env('LARADOCS_SEO_X_CARD', 'summary_large_image'),

        // Open Graph type emitted for documentation pages.
        'type' => env('LARADOCS_SEO_TYPE', 'article'),

        // Robots directive. Null keeps the package's crawler-friendly default;
        // set e.g. "noindex, nofollow" to keep the docs out of search engines.
        'robots' => env('LARADOCS_SEO_ROBOTS'),

        // JSON-LD structured data emitted into the document <head>.
        'schema' => [
            'article' => (bool) env('LARADOCS_SEO_SCHEMA_ARTICLE', true),
            'breadcrumbs' => (bool) env('LARADOCS_SEO_SCHEMA_BREADCRUMBS', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | RSS / Atom Feed
    |--------------------------------------------------------------------------
    |
    | An XML feed is served at {prefix}/feed.xml listing the N most-recently-
    | updated visible pages, ordered by `updated_at` front-matter (falling back
    | to the file's mtime).
    |
    | "format"   Output format: rss (RSS 2.0) | atom (Atom 1.0).
    | "limit"    Maximum number of items to include.
    |
    */

    'feed' => [
        'format' => env('LARADOCS_FEED_FORMAT', 'rss'),
        'limit' => (int) env('LARADOCS_FEED_LIMIT', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | robots.txt
    |--------------------------------------------------------------------------
    |
    | A default robots.txt is served at {prefix}/robots.txt. It always emits a
    | Sitemap: pointer at the package's sitemap and, by default, allows every
    | crawler.
    |
    | When `laradocs.enabled` is false the entire body is replaced with a
    | "Disallow: /" directive — so search engines keep the docs out of their
    | index without ever needing to fetch a page.
    |
    | "rules" Custom User-agent groups. Each entry is an associative array:
    |
    |   [
    |     'user_agent' => '*',                    // string or array of strings
    |     'allow'      => ['/'],                  // string or array (optional)
    |     'disallow'   => ['/private/'],          // string or array (optional)
    |   ]
    |
    | Leave the array empty to keep the default "allow everything" block.
    |
    */

    'robots' => [
        'rules' => [
            // ['user_agent' => '*', 'disallow' => ['/_laradocs/']],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    |
    | Powers the ⌘K command palette's full-text search over page content.
    |
    | "driver"    auto | scout | json.
    |   auto  — use Laravel Scout (Meilisearch / Typesense / Algolia) when it's
    |           installed and configured, otherwise fall back to the JSON index.
    |   scout — force Scout (falls back to JSON if Scout isn't installed).
    |   json  — always use the built-in, dependency-free JSON index.
    | "index"     Index name used by Scout engines.
    | "limit"     Maximum results returned per query.
    | "min_chars" Shortest query that triggers a search.
    | "max_chars" Cap on indexed body text per page (0 = no cap).
    | "exclude"   fnmatch slug patterns always excluded from the index.
    |               E.g. ['internal/*', 'drafts/*']
    | "include"   fnmatch slug patterns for an allow-list. When non-empty,
    |               only matching slugs are indexed regardless of other settings.
    |               E.g. ['guide/*', 'reference/*']
    | "rank"      Rank multipliers by fnmatch slug pattern. First matching
    |               pattern wins; combined with the page's own search_rank
    |               front-matter value (both default to 1.0 = no change).
    |               Values > 1.0 boost; values < 1.0 demote.
    |               E.g. ['guide/*' => 2.0, 'changelog' => 0.5]
    |               Only affects the built-in JSON engine; Scout engines use
    |               their own external ranking.
    |
    | Per-page opt-out: add `search: false` to a page's front-matter.
    | Per-page rank:    add `search_rank: 2.0` to a page's front-matter.
    | Hidden pages (`hidden: true`) are never indexed.
    |
    */

    'search' => [
        'driver' => env('LARADOCS_SEARCH_DRIVER', 'auto'),
        'index' => env('LARADOCS_SEARCH_INDEX', 'laradocs'),
        'limit' => (int) env('LARADOCS_SEARCH_LIMIT', 20),
        'min_chars' => (int) env('LARADOCS_SEARCH_MIN_CHARS', 2),
        'max_chars' => (int) env('LARADOCS_SEARCH_MAX_CHARS', 10000),
        'exclude' => [],
        'include' => [],
        'rank' => [],
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

    /*
    |--------------------------------------------------------------------------
    | HTTP API
    |--------------------------------------------------------------------------
    |
    | Controls the JSON API endpoints (_laradocs/api/tree and _laradocs/api/search).
    |
    | "rate_limit"  Requests per minute allowed per IP address. Override or
    |               disable programmatically via the Laradocs facade in a
    |               service provider's boot() method:
    |
    |                 Laradocs::rateLimit(false);              // disable
    |                 Laradocs::rateLimit(120);                // 120 rpm
    |                 Laradocs::rateLimit(fn ($req) => ...);   // full control
    |
    */

    'api' => [
        'rate_limit' => (int) env('LARADOCS_API_RATE_LIMIT', 60),
    ],

];

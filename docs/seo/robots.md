---
title: Robots
description: A default robots.txt that advertises the sitemap and respects the master switch.
order: 2
---

# robots.txt

Laradocs serves a `robots.txt` at:

```
GET {prefix}/robots.txt
```

For the default prefix, that's `/docs/robots.txt`. The default body allows every
crawler and points at the package's [sitemap](/docs/seo/sitemap):

```
User-agent: *
Allow: /

Sitemap: https://example.com/docs/sitemap.xml
```

## Disabling the docs

When `LARADOCS_ENABLED=false` the entire body collapses to a disallow-all
directive — no Sitemap pointer, no rules:

```
User-agent: *
Disallow: /
```

The `robots.txt` route stays available so crawlers receive a clear "don't index
this" signal, rather than a 404 they might retry later.

## Custom rules

Override the rule blocks via the `laradocs.robots.rules` config. Each entry is
an associative array describing one User-agent group:

```php
// config/laradocs.php
'robots' => [
    'rules' => [
        [
            'user_agent' => ['GPTBot', 'CCBot'],
            'disallow' => ['/'],
        ],
        [
            'user_agent' => '*',
            'allow' => ['/'],
            'disallow' => ['/_laradocs/'],
        ],
    ],
],
```

Renders as:

```
User-agent: GPTBot
User-agent: CCBot
Disallow: /

User-agent: *
Disallow: /_laradocs/
Allow: /

Sitemap: https://example.com/docs/sitemap.xml
```

`user_agent`, `allow` and `disallow` each accept a single string or an array of
strings. Leave `rules` empty to keep the default permissive block.

---
title: Media
description: Serve the images, video and downloads that live beside your markdown.
order: 7
---

# Media

Markdown is written next to the files it points at:

```markdown
![Architecture](diagram.png)
![Onboarding](../img/onboarding.mp4)
```

Those sources resolve to nothing once rendered, because the docs directory is
not public. `media.source` says where the files actually live:

| Value | Where the file is |
| --- | --- |
| `public` | Already public. Sources are left exactly as authored. The default. |
| `relative` | Beside the markdown, inside `docs.path`. |
| `disk` | On the filesystem disk named in `media.disk`. |

```php
// config/laradocs.php
'media' => [
    'source' => env('LARADOCS_MEDIA_SOURCE', 'public'),
    'disk' => env('LARADOCS_MEDIA_DISK'),
    'types' => ['image/*', 'video/*', 'audio/*', 'application/pdf'],
    'signed' => (bool) env('LARADOCS_MEDIA_SIGNED', false),
    'ttl' => env('LARADOCS_MEDIA_TTL'),
],
```

Anything but `public` registers `<prefix>/_media/<path>` and points the sources
in rendered pages at it. Nothing else changes: you keep writing plain markdown.

## Relative sources

A source is resolved against the page that wrote it, so `../img/diagram.png` on
`guide/intro.md` is `img/diagram.png` in the docs directory. A page that names a
file which is not there keeps its source untouched, which is what you want while
you are still moving files around.

## What may be served

`media.types` uses the notation of Laravel's `mimetypes` validation rule: an
exact type, or a group with a wildcard subtype.

```php
'types' => ['image/*', 'application/pdf'],
```

A path is judged twice. First by what its extension maps to, which costs nothing
and is what decides whether a page's source is rewritten at all. Then, on the way
out, by what the file actually contains — so a script renamed to `.png` is
refused rather than handed over.

Markdown falls outside every default type, and a path that climbs out of the
source is refused, so neither your documents nor anything above them is
reachable through this route.

<x-callout type="warning" title="SVG is executable">
`image/*` includes `image/svg+xml`, and an SVG can carry script. If your media
comes from anywhere but your own team, narrow `media.types` or serve it from a
separate origin.
</x-callout>

## Hotlinking

Media URLs are signed once they are served from here, so a link copied onto
another site stops working. Nothing to switch on.

The default costs nothing, because a signature without a `ttl` is deterministic:
the URL is the same on every render, so browsers and CDNs cache the file as they
would any other image.

```dotenv
LARADOCS_MEDIA_TTL=60     # minutes; leave empty for no expiry
LARADOCS_MEDIA_SIGNED=false
```

Setting a `ttl` puts an expiry in the URL, which changes it on every render.
Hotlinks then go stale on their own, at the cost of re-downloading media that
would otherwise have been cached. Worth it for media you do not want mirrored;
not worth it for a screenshot in a guide.

Signatures are computed over the full URL, so an application whose `APP_URL` or
proxy headers do not match what visitors actually request will reject its own
media with a 403. If that is the situation you are in, `LARADOCS_MEDIA_SIGNED=false`
is a reasonable answer while you sort the proxy out.

Signatures are applied on the way out of the HTML cache, never inside it. That
matters: rendered pages are cached per file with no request in the key, so a
signature baked into a cache entry would either expire for every reader at once
or never expire at all.

A signature travels with the URL. It stops a link working somewhere else; it
does not tell one reader from another. If a document is restricted, the media on
it still needs the same treatment as any other asset your application serves.

---
title: Analytics
description: Wire your docs to a privacy-friendly analytics provider.
order: 6
---

# Analytics

Laradocs ships drop-in support for popular analytics providers. Each one is
opt-in — set the site identifier and the appropriate snippet is injected into
the `<head>` of every docs page. No identifier, no script.

## Fathom

[Fathom Analytics](https://usefathom.com) is privacy-first, GDPR-compliant
and cookie-free.

```dotenv
LARADOCS_FATHOM_SITE=ABCDEFGH
```

That's it — page views start arriving in your Fathom dashboard.

### Options

| Option | Env | Default |
|---|---|---|
| `analytics.fathom.site` | `LARADOCS_FATHOM_SITE` | `null` |
| `analytics.fathom.script` | `LARADOCS_FATHOM_SCRIPT` | `https://cdn.usefathom.com/script.js` |
| `analytics.fathom.spa` | `LARADOCS_FATHOM_SPA` | `null` |

`script` lets you point at a [custom domain](https://usefathom.com/support/custom-domains)
to side-step ad-blockers. `spa` accepts `auto`, `history`, or `hash` — see
Fathom's docs if you've layered client-side routing on top of Laradocs.

## Disabling

Unset the env var (or set `analytics.<provider>.site` to `null`) and the
provider's script is removed entirely.

---
title: Analytics
description: Wire your docs to a privacy-friendly analytics provider.
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

## Google Analytics

[Google Analytics 4](https://analytics.google.com) is the default for many
teams already invested in the Google ecosystem.

```dotenv
LARADOCS_GA_MEASUREMENT_ID=G-XXXXXXXXXX
```

The official `gtag.js` snippet is injected and a `config` call is fired on
load. To anonymise visitor IPs (recommended if you serve EU traffic):

```dotenv
LARADOCS_GA_ANONYMIZE_IP=true
```

### Options

| Option | Env | Default |
|---|---|---|
| `analytics.google.measurement_id` | `LARADOCS_GA_MEASUREMENT_ID` | `null` |
| `analytics.google.anonymize_ip` | `LARADOCS_GA_ANONYMIZE_IP` | `false` |

> [!NOTE]
> Fathom and Google Analytics can both be enabled at the same time — they
> coexist happily and you'll see traffic in both dashboards.

## Disabling

Unset the env var (or set `analytics.<provider>.site` to `null`) and the
provider's script is removed entirely.

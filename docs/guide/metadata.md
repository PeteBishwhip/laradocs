---
title: Metadata
description: The front-matter fields Laradocs understands.
order: 2
---

# Metadata

Each page may declare YAML front-matter. Every field is optional.

```markdown
---
title: Getting Started
description: Install and configure the package.
slug: getting-started
order: 2
hidden: false
group: Basics
badge: New
icon: rocket
tags: [intro, setup]
updated_at: 2026-06-01
author: Pete
image: /og/getting-started.png
redirect: null
---
```

| Field | Purpose |
|---|---|
| `title` | Display title (falls back to the filename) |
| `description` | Used for the `<meta name="description">` tag |
| `order` | Sort order within a section (ascending) |
| `hidden` | Hide from navigation while keeping the page reachable |
| `group` | Bucket the page under a sidebar heading |
| `badge` | A small label shown next to the sidebar link |
| `redirect` | Redirect this slug to another page or URL |

> [!IMPORTANT]
> Unknown front-matter keys are preserved and available through
> `$document->metadata->get('your_key')`.

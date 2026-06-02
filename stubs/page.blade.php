---
title: {{ $title }}
{!! $group !== null ? "group: {$group}" : '# group: Guides              # Bucket this page sits under in the sidebar' !!}
{!! $order !== null ? "order: {$order}" : '# order: 1                   # Lower numbers appear first (default 999)' !!}
# description: A short summary used for <meta> tags and SEO
# slug: custom-url           # Override the URL slug (defaults to the file path)
# hidden: true               # Hide from the sidebar and listings
# badge: New                 # Small label shown next to the title in the sidebar
# icon: book                 # Icon name (consumed by your views/macros)
# tags: [intro, basics]      # Free-form tags
# updated_at: 2026-01-01     # Shown in the page footer when set
# author: Jane Doe
# layout: docs               # Override the Blade layout used to render this page
# image: /img/social.png     # Social/OG image
# redirect: /docs/other      # Permanent redirect to another URL
---

# {{ $title }}

Start writing your documentation here.

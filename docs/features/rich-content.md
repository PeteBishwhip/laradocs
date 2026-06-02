---
title: Rich Content
description: Callouts, code, images, video and formatting.
order: 3
---

# Rich Content

## Formatting

**Bold**, _italic_, ~~strikethrough~~, `inline code`, [links](https://laravel.com)
and > blockquotes all render with sensible default styling.

## Callouts

Use GitHub-style alerts:

```markdown
> [!NOTE]
> Useful information users should know.

> [!WARNING]
> Something that needs caution.
```

> [!NOTE]
> Useful information users should know.

> [!DANGER]
> A destructive action — proceed carefully.

## Code blocks

Fenced code blocks get a language label and a copy button:

```php
Route::get('/docs', function () {
    return view('laradocs::layout');
});
```

## Images

Images are lazy-loaded; a markdown title becomes a caption:

```markdown
![A diagram](/img/architecture.png "Figure 1: request lifecycle")
```

## Video

Local files become a `<video>` player, and YouTube / Vimeo links become
responsive embeds:

```markdown
![demo](/media/demo.mp4)

[Watch the intro](https://youtu.be/dQw4w9WgXcQ)
```

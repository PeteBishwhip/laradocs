# Rich content syntax

Everything here is enabled by default via `laradocs.parser.extensions`.

## Callouts

GitHub-style alert blockquotes. Types: `NOTE`, `TIP`, `IMPORTANT`, `WARNING`,
`DANGER`, `CAUTION`. The type is case-insensitive.

```markdown
> [!NOTE]
> Callouts render as styled boxes.

> [!WARNING]
> Back up your database before running this migration.
```

## Code blocks

A fenced block with a language gets a copy button and a language label.

````markdown
```php
echo 'Hello from a highlighted, copyable block';
```
````

## Images with captions

The markdown `title` becomes a caption. Images are lazy-loaded and zoomable.

```markdown
![Architecture diagram](/img/architecture.png "How requests flow through Laradocs")
```

## Video

Local `.mp4`, `.webm`, `.ogg` and `.mov` files embed via image syntax. YouTube
(`youtu.be/…`, `youtube.com/watch?v=…`) and Vimeo (`vimeo.com/…`) links become
embedded iframes.

```markdown
![Demo](/media/demo.mp4)

[Watch the intro](https://youtu.be/dQw4w9WgXcQ)
```

## Mermaid diagrams

A fenced `mermaid` block renders to SVG.

````markdown
```mermaid
graph TD
  A[Markdown] --> B[Laradocs] --> C[/docs]
```
````

## KaTeX math

Inline `$…$` and block `$$ … $$`.

```markdown
Inline math like $E = mc^2$ renders inline.

$$
\int_0^\infty e^{-x}\,dx = 1
$$
```

## Also enabled by default

GFM (tables, task lists, strikethrough), footnotes (`[^1]`), attribute lists
(`{.class #id}`), and automatic heading anchors.

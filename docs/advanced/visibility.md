---
title: Visibility
description: Decide which documents a reader may see.
order: 7
---

# Visibility

Some documentation is not for everyone: an internal runbook, a section only
customers on a plan should reach, a page that follows a role in your own
application. Bind a `DocumentVisibility` rule and laradocs asks it which
documents the current reader may see.

```php
namespace App\Docs;

use Illuminate\Support\Facades\Gate;
use Laradocs\Contracts\DocumentVisibility;
use Laradocs\Documents\Document;
use Laradocs\Documents\DocumentCollection;

final class Permissions implements DocumentVisibility
{
    public function filter(DocumentCollection $documents): DocumentCollection
    {
        return $documents
            ->filter(fn (Document $document): bool => $this->allows($document))
            ->values();
    }

    private function allows(Document $document): bool
    {
        $ability = $document->metadata->get('permission');

        return $ability === null || Gate::allows((string) $ability);
    }
}
```

```php
// A service provider
$this->app->bind(DocumentVisibility::class, Permissions::class);
```

Nothing is bound by default, so a site that does not need this pays nothing:
no wrapper, no calls, no change in behaviour.

## What it covers

Every read goes through it, because the rule wraps the document loader rather
than sitting somewhere further along:

- the navigation tree, the pager and the command palette
- search results and the search index
- `sitemap.xml`, the feeds, `llms.txt` and `llms-full.txt`
- the tag pages and the MCP tools
- a direct hit on the page's URL, which 404s rather than rendering

That last one is why the rule belongs at the loader. Filtering the navigation
alone would leave the page reachable by anyone who knows the slug.

## Why a collection, not one document

A rule is often about more than the page in front of you: a permission on a
section's `_index.md` applying to everything beneath it, a page inheriting from
a parent, a page visible only when a sibling is. Handing over the whole
collection means those rules can be written at all.

Returning the collection unchanged is a valid answer.

## Cost

The rule is asked **once per request**, however often the documents are read —
laradocs reads them several times while rendering a single page, and the loader
is a scoped binding that keeps both the read and the decision.

Everything cached downstream keys off the document set it was built from, so the
tree, the search index, the sitemap and the feeds each get an entry per distinct
set of visible documents. Two readers who see the same pages share one entry;
a reader who sees fewer gets their own. Nothing has to be tagged or invalidated
per reader.

Rendered HTML is cached per file with no reader in the key, which is safe
because a page is only ever served to someone the rule allows: `find()` returns
nothing for anyone else, long before the cache is consulted.

## Warming caches

Work that is not being done on a reader's behalf can step around the rule:

```php
$laradocs->withoutVisibility(function () use ($laradocs) {
    $laradocs->all()->each(fn ($document) => $laradocs->render($document));
});
```

`laradocs:cache` already does this, so every page is pre-rendered whoever is
allowed to read it. `laradocs:index` deliberately does not: a search index is
one artifact shared by every reader, so it is built from what the rule allows
and nothing more.

<x-callout type="warning" title="A rule is not authentication">
Visibility decides what a reader is shown. It does not authenticate them: that
is your application's job, and the rule should read from whatever it already
knows about the current user.
</x-callout>

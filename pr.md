# Stop tabs rewriting fenced code that only shows the syntax

`docs/content/rich-content.md` documents the code-tab shorthand by putting the
example in a five-backtick fence, which is the normal way to show fence syntax.
The rendered page does not show that example — it shows the *output* of the
transform instead:

```
<div class="laradocs-code-tab-pending" data-tab="PHP">

```php
$response = Http::get('/api/users');
```

</div>
```

So the one page that explains the feature is the page the feature breaks.

## Cause

`TabsMarkdownExtension` runs two regexes straight over the raw markdown:

```php
'/^(`{3,}|~{3,})([^\n]*\btab:(\S+)[^\n]*)\n(.*?)^\1[ \t]*$/ms'   // code tabs
'/^:::[ \t]+tabs((?:[ \t][^\n]*)?)(\n.*?)^:::[ \t]*$/ms'          // content tabs
```

Neither knows about CommonMark §4.5: a fence opened with N backticks is literal
until a fence of at least N. The inner ```` ```php tab:PHP ```` matches on its
own terms, so it is rewritten even though it sits inside a longer fence.

`CodeAwareReplacer` already implements that rule correctly, and six extensions
go through it — `Macro`, `Variable`, `Katex`, `Icon`, `BladeComponent` and
`LintCommand`. `TabsMarkdownExtension` is the only one that does not.

## Fix

It cannot use `protect()` as-is, because it is the one transform that *must*
operate on fenced blocks. So `protect()` gains an optional predicate that keeps
matching blocks unmasked:

```php
[$markdown, $restore] = CodeAwareReplacer::protect(
    $markdown,
    static fn (string $opener): bool => preg_match(self::TAB_INFO, $opener) === 1,
);
```

A tab-tagged fence at top level stays visible to the regexes. Every other fence
is masked, so anything nested inside one is never seen. The nested tab-tagged
fence in the docs is not offered to the predicate at all — its enclosing fence is
masked whole, before the scanner ever reaches it.

## A second bug this surfaced

The placeholder was `"\x00laradocs-code-N\x00"`, and `\0` is in PHP's default
trim charlist. `transformContentTabs` calls `rtrim()` on tab-panel bodies, which
sheared the delimiter off and left the placeholder unrestorable — a raw
`laradocs-code-0` rendered into the page. The delimiter is now `\x1F`.

Nothing outside `CodeAwareReplacer` knows the token format, so this is contained.
It was latent rather than new: any caller that trims protected markdown would
have hit it, and this is the first one that does.

## Tests

Four added, and each one fails without the corresponding source change:

- code-tab syntax inside a longer fence stays literal
- content-tab syntax inside a longer fence stays literal
- a placeholder survives the caller trimming the masked string
- the `except` predicate keeps a matching opener unmasked, and does not reach one
  nested inside another fence

`rich-content.md` was also rendered end to end: no `laradocs-code-tab-pending`
and no placeholder in the output, with `tab:PHP` and `::: tabs` both appearing
literally, as the page intends.

## Suite

Pint, PHPStan and Pest pass — 1121 tests, 4 skipped, up from 1117 by the four
added here.

`composer test` still exits non-zero on psalm, but it does so on `main` too:
49 errors with and without this change, 44 of them inside
`vendor/laravel/framework` (`Collection.php`, `Command.php`), which looks like
`psalm-baseline.xml` trailing a framework bump. The remaining five are in
`BuildsSiteArtifacts`, `LlmsFullTxtBuilder` and `DocumentRouter`, none of which
this PR touches. Worth a separate baseline refresh.

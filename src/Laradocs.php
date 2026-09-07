<?php

declare(strict_types=1);

namespace Laradocs;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laradocs\Ai\ChatExchange;
use Laradocs\Ai\ChatRegistry;
use Laradocs\Ai\ChatRequest;
use Laradocs\Cache\DocumentCache;
use Laradocs\Concerns\BuildsSiteArtifacts;
use Laradocs\Contracts\DocumentContentRenderer;
use Laradocs\Contracts\DocumentLoader;
use Laradocs\Contracts\DocumentParser;
use Laradocs\Documents\Document;
use Laradocs\Documents\DocumentCollection;
use Laradocs\Documents\DocumentTree;
use Laradocs\Documents\Tag;
use Laradocs\Loaders\VisibilityLoader;
use Laradocs\Macros\MacroRegistry;
use Laradocs\Media\MediaRewriter;
use Laradocs\Routing\DocumentLinkRewriter;
use Laradocs\Search\SearchIndexBuilder;
use Laradocs\Support\Locale;
use Laradocs\Support\RateLimiterConfig;
use Laradocs\Variables\VariableRegistry;

/**
 * The package's primary entry point and fluent configuration surface.
 */
final class Laradocs
{
    use BuildsSiteArtifacts;

    /**
     * @param  array<int, string>  $searchExclude
     * @param  array<int, string>  $searchInclude
     * @param  array<string, float>  $searchRank
     * @param  array<int, DocumentContentRenderer>  $contentRenderers
     */
    public function __construct(
        private readonly DocumentLoader $loader,
        private readonly DocumentParser $parser,
        private readonly DocumentCache $cache,
        private readonly VariableRegistry $variables,
        private readonly MacroRegistry $macros,
        private readonly RateLimiterConfig $rateLimiterConfig,
        private readonly ChatRegistry $chat,
        private readonly string $indexName = '_index',
        private readonly int $searchMaxChars = 10000,
        private readonly array $searchExclude = [],
        private readonly array $searchInclude = [],
        private readonly array $searchRank = [],
        private readonly array $contentRenderers = [],
        private readonly ?MediaRewriter $media = null,
        private readonly ?DocumentLinkRewriter $links = null,
    ) {}

    /**
     * Register variables for interpolation, as an array or a closure.
     *
     * **Boot-time only** for eager arrays — call from a service provider's
     * `boot()` method. Eager values mutate a singleton and persist into every
     * subsequent request on long-lived workers (Octane / RoadRunner).
     * Closure providers are re-invoked per read and may safely reference
     * per-request state such as the authenticated user or active locale.
     *
     * @param  array<string, mixed>|Closure  $values
     */
    public function variables(array|Closure $values): self
    {
        $this->variables->register($values);

        return $this;
    }

    /**
     * Share a single named value with docs content and views.
     *
     * **Boot-time only.** Mutations to the underlying {@see VariableRegistry}
     * singleton persist into every subsequent request on long-lived workers
     * (Octane / RoadRunner). Call this exclusively from a service provider's
     * `boot()` method; use a closure via {@see variables()} for per-request
     * values instead.
     */
    public function share(string $key, mixed $value): self
    {
        $this->variables->set($key, $value);

        return $this;
    }

    /**
     * Override or disable the API rate limiter.
     *
     * Pass an integer to set a per-minute limit, a closure for full control
     * over the Limit object, or false to disable rate limiting entirely.
     *
     * Call this in a service provider's boot() method:
     *
     *   Laradocs::rateLimit(false);             // disable
     *   Laradocs::rateLimit(120);               // 120 rpm per IP
     *   Laradocs::rateLimit(fn ($req) => ...);  // full control
     */
    public function rateLimit(Closure|int|false $resolver): self
    {
        $this->rateLimiterConfig->set($resolver);

        return $this;
    }

    /**
     * Register a callback that determines whether cookie persistence is enabled
     * for the current request.
     *
     * Use this in your application's service provider once you have a working
     * cookie-consent mechanism in place, instead of the static `locale.cookie`
     * config flag:
     *
     *   Laradocs::cookiesEnabled(fn () => auth()->user()?->hasConsented('cookies'));
     *   Laradocs::cookiesEnabled(fn () => Cookie::get('cookie_consent') === 'true');
     *
     * The callback is evaluated per request so it can inspect session state,
     * consent cookies, or any other runtime condition. When no callback is
     * registered the `laradocs.locale.cookie` config value is used (default:
     * `false`).
     *
     * Pass `null` to clear a previously registered callback and revert to the
     * config value.
     */
    public function cookiesEnabled(?Closure $resolver): self
    {
        Locale::setCookieResolver($resolver);

        return $this;
    }

    /**
     * Register a callback that runs once the AI chat has answered.
     *
     * This is where a deployment meters what the assistant costs and keeps
     * what it said. The callback receives a
     * {@see ChatExchange}: the question, the answer, the token
     * usage, the provider and model that produced it, the tools it called, and
     * the reader it answered. Register as many as you need; each is called in
     * turn, streamed answers included, and the return value is ignored.
     *
     *   Laradocs::onChat(function (ChatExchange $exchange): void {
     *       AiUsage::create([
     *           'user_id' => $exchange->user()?->getAuthIdentifier(),
     *           'question' => $exchange->question(),
     *           'tokens' => $exchange->usage->total(),
     *           'model' => $exchange->model,
     *       ]);
     *   });
     *
     * A callback that throws is reported and the rest still run: by this point
     * the answer has already been produced and, for a streamed answer, already
     * reached the reader.
     *
     * **Boot-time only.** Registrations mutate a singleton and persist into
     * every subsequent request on long-lived workers (Octane / RoadRunner).
     *
     * @param  Closure(ChatExchange): mixed  $handler
     */
    public function onChat(Closure $handler): self
    {
        $this->chat->onChat($handler);

        return $this;
    }

    /**
     * Append context to the AI chat's instructions, per request.
     *
     * Use it to tell the assistant something about the reader that the
     * documentation cannot know: their plan, their tenant, which features they
     * have switched on. The callback receives a
     * {@see ChatRequest} and may return a string, or anything
     * iterable of strings; each becomes a line of context.
     *
     *   Laradocs::chatContext(fn (ChatRequest $request) => $request->user
     *       ? 'The reader is on the ' . $request->user->plan . ' plan.'
     *       : null);
     *
     * Callbacks are invoked per request, so they may safely read per-request
     * state, but the registration itself is **boot-time only**.
     *
     * @param  Closure(ChatRequest): mixed  $resolver
     */
    public function chatContext(Closure $resolver): self
    {
        $this->chat->context($resolver);

        return $this;
    }

    /**
     * Hand the AI chat extra tools, per request.
     *
     * For tools the config cannot describe: a Laravel AI SDK tool of your own,
     * one of your MCP server's tools, another agent. The callback receives a
     * {@see ChatRequest} and returns a tool or anything
     * iterable of tools, which join the documentation tools rather than
     * replacing them.
     *
     *   Laradocs::chatTools(fn (ChatRequest $request) => $request->user
     *       ? [new LookUpSubscription($request->user)]
     *       : []);
     *
     * Callbacks are invoked per request; the registration is **boot-time
     * only**.
     *
     * @param  Closure(ChatRequest): mixed  $resolver
     */
    public function chatTools(Closure $resolver): self
    {
        $this->chat->tools($resolver);

        return $this;
    }

    /**
     * Decide who may talk to the AI chat, for the cases a guard and a Gate
     * ability cannot express.
     *
     * The callback receives the HTTP request and returns whether to allow it.
     * It is consulted after the configured `laradocs.ai.auth` guard and gate,
     * so it narrows access rather than widening it, and a false answer is a
     * 403.
     *
     *   Laradocs::chatAuthorize(fn (Request $request) => $request->user()?->hasVerifiedEmail());
     *
     * Pass null to clear a previously registered callback.
     *
     * **Boot-time only.**
     *
     * @param  (Closure(Request): mixed)|null  $callback
     */
    public function chatAuthorize(?Closure $callback): self
    {
        $this->chat->authorize($callback);

        return $this;
    }

    /**
     * Register a reusable macro (closure or Blade view name).
     *
     * **Boot-time only.** Mutations to the underlying {@see MacroRegistry}
     * singleton persist into every subsequent request on long-lived workers
     * (Octane / RoadRunner). Call this exclusively from a service provider's
     * `boot()` method.
     */
    public function macro(string $name, Closure|string $handler): self
    {
        $this->macros->register($name, $handler);

        return $this;
    }

    /**
     * Read every document, whatever the configured visibility rule says.
     *
     * For work that is not being done on a reader's behalf — warming caches,
     * a console command, an export. Without a rule bound this simply runs the
     * callback.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function withoutVisibility(Closure $callback): mixed
    {
        return $this->loader instanceof VisibilityLoader
            ? $this->loader->withoutFiltering($callback)
            : $callback();
    }

    /**
     * Every document, unsorted, as loaded from disk.
     */
    public function all(): DocumentCollection
    {
        return $this->loader->all();
    }

    /**
     * The assembled, cached navigation tree.
     */
    public function tree(): DocumentTree
    {
        $documents = $this->all();

        return $this->cache->rememberTree(
            $documents,
            fn (): DocumentTree => DocumentTree::fromDocuments($documents, $this->indexName)
        );
    }

    /**
     * Resolve a slug to a document with its HTML rendered (and cached).
     */
    public function find(string $slug): ?Document
    {
        $document = $this->loader->find($slug);

        return $document === null ? null : $document->withHtml($this->render($document));
    }

    /**
     * Every tag declared across visible documents, each paired with the
     * (visible, ordered) pages that carry it. Sorted alphabetically by label.
     *
     * Hidden documents never contribute, so a tag used only by hidden pages
     * does not appear here.
     *
     * @return Collection<int, Tag>
     */
    public function tags(): Collection
    {
        /** @var array<string, array{label: string, documents: array<int, Document>}> $buckets */
        $buckets = [];

        foreach ($this->all()->visible() as $document) {
            foreach ($document->metadata->tags as $label) {
                $label = trim($label);
                $slug = Str::slug($label);

                if ($label === '' || $slug === '') {
                    continue;
                }

                // First spelling of a tag wins as the display label, so
                // "API" and "api" collapse to one entry rather than two.
                $buckets[$slug] ??= ['label' => $label, 'documents' => []];
                $buckets[$slug]['documents'][] = $document;
            }
        }

        return collect($buckets)
            ->map(fn (array $bucket, string $slug): Tag => new Tag(
                $slug,
                $bucket['label'],
                (new DocumentCollection($bucket['documents']))->ordered(),
            ))
            ->sortBy(fn (Tag $tag): string => mb_strtolower($tag->label, 'UTF-8'), SORT_NATURAL)
            ->values();
    }

    /**
     * Resolve a single tag (matched by its slug) to its visible documents, or
     * null when no visible document carries it.
     */
    public function tag(string $slug): ?Tag
    {
        $slug = Str::slug($slug);

        return $this->tags()->first(fn (Tag $tag): bool => $tag->slug === $slug);
    }

    /**
     * The landing document for the docs root, if any.
     */
    public function home(): ?Document
    {
        $document = $this->tree()->rootDocument
            ?? $this->all()->visible()->ordered()->first();

        return $document === null ? null : $document->withHtml($this->render($document));
    }

    /**
     * The pre-rendered, cached full-text search index: one entry per visible,
     * searchable page. Busts automatically when any document changes.
     *
     * @return array<int, array{slug: string, title: string, group: string, content: string, rank: float}>
     */
    public function searchIndex(): array
    {
        $documents = $this->all();

        return $this->cache->rememberSearchIndex(
            $documents,
            fn (): array => (new SearchIndexBuilder)->build(
                $documents,
                fn (Document $document): string => $this->render($document),
                $this->searchMaxChars,
                $this->searchExclude,
                $this->searchInclude,
                $this->searchRank,
            )
        );
    }

    /**
     * Render (and cache) a document's markdown to HTML.
     *
     * Media sources and links between documents are resolved on the way out of
     * the cache rather than inside it: the document is known here, so a relative
     * path resolves against the page that wrote it, and a signed media URL never
     * becomes part of a cache entry every reader shares.
     */
    public function render(Document $document): string
    {
        $html = $this->cache->rememberHtml(
            $document,
            function () use ($document): string {
                foreach ($this->contentRenderers as $renderer) {
                    if ($renderer->supports($document)) {
                        return $renderer->render($document);
                    }
                }

                return $this->parser->parse($document->markdown);
            }
        );

        $html = $this->media?->rewrite($html, $document) ?? $html;

        return $this->links?->rewrite($html, $document) ?? $html;
    }

    /**
     * The fully resolved variable values.
     *
     * @return array<string, mixed>
     */
    public function variableValues(): array
    {
        return $this->variables->all();
    }

    public function variableRegistry(): VariableRegistry
    {
        return $this->variables;
    }

    public function macroRegistry(): MacroRegistry
    {
        return $this->macros;
    }

    public function cache(): DocumentCache
    {
        return $this->cache;
    }
}

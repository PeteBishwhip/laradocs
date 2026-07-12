<?php

declare(strict_types=1);

namespace Laradocs;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laradocs\Cache\DocumentCache;
use Laradocs\Contracts\DocumentContentRenderer;
use Laradocs\Contracts\DocumentLoader;
use Laradocs\Contracts\DocumentParser;
use Laradocs\Documents\Document;
use Laradocs\Documents\DocumentCollection;
use Laradocs\Documents\DocumentTree;
use Laradocs\Documents\Tag;
use Laradocs\Macros\MacroRegistry;
use Laradocs\Routing\FeedBuilder;
use Laradocs\Routing\SitemapBuilder;
use Laradocs\Search\SearchIndexBuilder;
use Laradocs\Support\Locale;
use Laradocs\Support\RateLimiterConfig;
use Laradocs\Variables\VariableRegistry;

/**
 * The package's primary entry point and fluent configuration surface.
 */
final class Laradocs
{
    /**
     * @readonly
     * @var \Laradocs\Contracts\DocumentLoader
     */
    private $loader;
    /**
     * @readonly
     * @var \Laradocs\Contracts\DocumentParser
     */
    private $parser;
    /**
     * @readonly
     * @var \Laradocs\Cache\DocumentCache
     */
    private $cache;
    /**
     * @readonly
     * @var \Laradocs\Variables\VariableRegistry
     */
    private $variables;
    /**
     * @readonly
     * @var \Laradocs\Macros\MacroRegistry
     */
    private $macros;
    /**
     * @readonly
     * @var \Laradocs\Support\RateLimiterConfig
     */
    private $rateLimiterConfig;
    /**
     * @readonly
     * @var string
     */
    private $indexName = '_index';
    /**
     * @readonly
     * @var int
     */
    private $searchMaxChars = 10000;
    /**
     * @var array<int, string>
     * @readonly
     */
    private $searchExclude = [];
    /**
     * @var array<int, string>
     * @readonly
     */
    private $searchInclude = [];
    /**
     * @var array<string, float>
     * @readonly
     */
    private $searchRank = [];
    /**
     * @var array<int, DocumentContentRenderer>
     * @readonly
     */
    private $contentRenderers = [];
    /**
     * @param  array<int, string>  $searchExclude
     * @param  array<int, string>  $searchInclude
     * @param  array<string, float>  $searchRank
     * @param  array<int, DocumentContentRenderer>  $contentRenderers
     */
    public function __construct(DocumentLoader $loader, DocumentParser $parser, DocumentCache $cache, VariableRegistry $variables, MacroRegistry $macros, RateLimiterConfig $rateLimiterConfig, string $indexName = '_index', int $searchMaxChars = 10000, array $searchExclude = [], array $searchInclude = [], array $searchRank = [], array $contentRenderers = [])
    {
        $this->loader = $loader;
        $this->parser = $parser;
        $this->cache = $cache;
        $this->variables = $variables;
        $this->macros = $macros;
        $this->rateLimiterConfig = $rateLimiterConfig;
        $this->indexName = $indexName;
        $this->searchMaxChars = $searchMaxChars;
        $this->searchExclude = $searchExclude;
        $this->searchInclude = $searchInclude;
        $this->searchRank = $searchRank;
        $this->contentRenderers = $contentRenderers;
    }

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
    public function variables($values): self
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
     * @param mixed $value
     */
    public function share(string $key, $value): self
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
     * @param \Closure|int|false $resolver
     */
    public function rateLimit($resolver): self
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
     * Register a reusable macro (closure or Blade view name).
     *
     * **Boot-time only.** Mutations to the underlying {@see MacroRegistry}
     * singleton persist into every subsequent request on long-lived workers
     * (Octane / RoadRunner). Call this exclusively from a service provider's
     * `boot()` method.
     * @param \Closure|string $handler
     */
    public function macro(string $name, $handler): self
    {
        $this->macros->register($name, $handler);

        return $this;
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
            function () use ($documents): DocumentTree {
                return DocumentTree::fromDocuments($documents, $this->indexName);
            }
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
                $buckets[$slug] = $buckets[$slug] ?? ['label' => $label, 'documents' => []];
                $buckets[$slug]['documents'][] = $document;
            }
        }

        return collect($buckets)
            ->map(function (array $bucket, string $slug): Tag {
                return new Tag(
                    $slug,
                    $bucket['label'],
                    (new DocumentCollection($bucket['documents']))->ordered(),
                );
            })
            ->sortBy(function (Tag $tag): string {
                return mb_strtolower($tag->label, 'UTF-8');
            }, SORT_NATURAL)
            ->values();
    }

    /**
     * Resolve a single tag (matched by its slug) to its visible documents, or
     * null when no visible document carries it.
     */
    public function tag(string $slug): ?Tag
    {
        $slug = Str::slug($slug);

        return $this->tags()->first(function (Tag $tag) use ($slug): bool {
            return $tag->slug === $slug;
        });
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
            function () use ($documents): array {
                return (new SearchIndexBuilder)->build(
                    $documents,
                    function (Document $document): string {
                        return $this->render($document);
                    },
                    $this->searchMaxChars,
                    $this->searchExclude,
                    $this->searchInclude,
                    $this->searchRank,
                );
            }
        );
    }

    /**
     * The rendered, cached sitemap XML listing every visible, non-redirected
     * page in tree order. Busts automatically when any document changes.
     */
    public function sitemap(): string
    {
        $documents = $this->all();

        return $this->cache->rememberSitemap(
            $documents,
            function (): string {
                return (new SitemapBuilder)->build($this->tree());
            }
        );
    }

    /**
     * The rendered, cached feed XML (RSS 2.0 or Atom 1.0) listing the N
     * most-recently-updated visible, non-redirected pages. Busts automatically
     * when any document changes.
     */
    public function feed(string $format, int $limit, string $feedUrl, string $siteTitle): string
    {
        $documents = $this->all();

        return $this->cache->rememberFeed(
            $documents,
            $format,
            function () use ($documents, $format, $limit, $feedUrl, $siteTitle): string {
                return (new FeedBuilder)->build($documents, $format, $limit, $feedUrl, $siteTitle);
            }
        );
    }

    /**
     * Render (and cache) a document's markdown to HTML.
     */
    public function render(Document $document): string
    {
        return $this->cache->rememberHtml(
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

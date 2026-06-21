<?php

declare(strict_types=1);

namespace Laradocs;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laradocs\Cache\DocumentCache;
use Laradocs\Contracts\DocumentLoader;
use Laradocs\Contracts\DocumentParser;
use Laradocs\Documents\Document;
use Laradocs\Documents\DocumentCollection;
use Laradocs\Documents\DocumentTree;
use Laradocs\Documents\Tag;
use Laradocs\Icons\IconRegistry;
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
     * @param  array<int, string>  $searchExclude
     * @param  array<int, string>  $searchInclude
     * @param  array<string, float>  $searchRank
     */
    public function __construct(
        private readonly DocumentLoader $loader,
        private readonly DocumentParser $parser,
        private readonly DocumentCache $cache,
        private readonly VariableRegistry $variables,
        private readonly MacroRegistry $macros,
        private readonly IconRegistry $icons,
        private readonly RateLimiterConfig $rateLimiterConfig,
        private readonly string $indexName = '_index',
        private readonly int $searchMaxChars = 10000,
        private readonly array $searchExclude = [],
        private readonly array $searchInclude = [],
        private readonly array $searchRank = [],
    ) {}

    /**
     * Register variables for interpolation, as an array or a closure.
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
     * Register a reusable macro (closure or Blade view name).
     */
    public function macro(string $name, Closure|string $handler): self
    {
        $this->macros->register($name, $handler);

        return $this;
    }

    /**
     * Register a custom icon set provider.
     *
     * The closure receives the icon name and variant string and must return a
     * raw SVG string, or an empty string when the icon is not found.
     *
     *   Laradocs::registerIconSet('phosphor', function (string $name, string $variant): string {
     *       return file_get_contents(resource_path("icons/phosphor/{$name}.svg")) ?: '';
     *   });
     *
     * @param  Closure(string, string): string  $provider
     */
    public function registerIconSet(string $name, Closure $provider): self
    {
        $this->icons->register($name, $provider);

        return $this;
    }

    /**
     * Every document, unsorted, as loaded from disk.
     *
     * @return DocumentCollection<int, Document>
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
            ->sortBy(fn (Tag $tag): string => Str::lower($tag->label), SORT_NATURAL)
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
     * The rendered, cached sitemap XML listing every visible, non-redirected
     * page in tree order. Busts automatically when any document changes.
     */
    public function sitemap(): string
    {
        $documents = $this->all();

        return $this->cache->rememberSitemap(
            $documents,
            fn (): string => (new SitemapBuilder)->build($this->tree())
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
            fn (): string => (new FeedBuilder)->build($documents, $format, $limit, $feedUrl, $siteTitle)
        );
    }

    /**
     * Render (and cache) a document's markdown to HTML.
     */
    public function render(Document $document): string
    {
        return $this->cache->rememberHtml(
            $document,
            fn (): string => $this->parser->parse($document->markdown)
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

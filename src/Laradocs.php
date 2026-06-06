<?php

declare(strict_types=1);

namespace Laradocs;

use Closure;
use Laradocs\Cache\DocumentCache;
use Laradocs\Contracts\DocumentLoader;
use Laradocs\Contracts\DocumentParser;
use Laradocs\Documents\Document;
use Laradocs\Documents\DocumentCollection;
use Laradocs\Documents\DocumentTree;
use Laradocs\Macros\MacroRegistry;
use Laradocs\Search\SearchIndexBuilder;
use Laradocs\Support\RateLimiterConfig;
use Laradocs\Variables\VariableRegistry;

/**
 * The package's primary entry point and fluent configuration surface.
 */
final class Laradocs
{
    public function __construct(
        private readonly DocumentLoader $loader,
        private readonly DocumentParser $parser,
        private readonly DocumentCache $cache,
        private readonly VariableRegistry $variables,
        private readonly MacroRegistry $macros,
        private readonly RateLimiterConfig $rateLimiterConfig,
        private readonly string $indexName = '_index',
        private readonly int $searchMaxChars = 10000,
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
     * Register a reusable macro (closure or Blade view name).
     */
    public function macro(string $name, Closure|string $handler): self
    {
        $this->macros->register($name, $handler);

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
     * @return array<int, array{slug: string, title: string, group: string, content: string}>
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
            )
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

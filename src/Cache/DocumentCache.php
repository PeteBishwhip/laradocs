<?php

declare(strict_types=1);

namespace Laradocs\Cache;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Laradocs\Documents\Document;
use Laradocs\Documents\DocumentCollection;
use Laradocs\Documents\DocumentTree;
use Laradocs\Support\CacheKey;

final class DocumentCache
{
    /**
     * @readonly
     * @var \Illuminate\Contracts\Cache\Repository
     */
    private $store;
    /**
     * @readonly
     * @var bool
     */
    private $enabled = true;
    /**
     * @readonly
     * @var int|null
     */
    private $ttl;
    public function __construct(Repository $store, bool $enabled = true, ?int $ttl = null)
    {
        $this->store = $store;
        $this->enabled = $enabled;
        $this->ttl = $ttl;
    }

    /**
     * Cache a document's rendered HTML, keyed by file path + mtime.
     *
     * @param  Closure(): string  $render
     */
    public function rememberHtml(Document $document, Closure $render): string
    {
        return $this->remember($this->documentKey($document), $render);
    }

    /**
     * Cache the navigation tree keyed by the combined document mtimes so it
     * busts whenever any file changes. The tree is stored as a pre-serialized
     * string so consumers with Laravel's `cache.serializable_classes => false`
     * setting still receive a real DocumentTree on a hit (Laravel would
     * otherwise return __PHP_Incomplete_Class for any cached object).
     *
     * @param  Closure(): DocumentTree  $build
     */
    public function rememberTree(DocumentCollection $documents, Closure $build): DocumentTree
    {
        $key = CacheKey::tree($this->signature($documents));

        if (! $this->enabled) {
            return $build();
        }

        if ($this->store->has($key)) {
            $cached = $this->store->get($key);

            if (is_string($cached)) {
                $value = @unserialize($cached);

                if ($value instanceof DocumentTree) {
                    return $value;
                }
            }
        }

        $value = $build();
        $this->put($key, serialize($value));
        $this->track($key);

        return $value;
    }

    /**
     * Cache the rendered sitemap XML, keyed by the combined document mtimes
     * so it busts whenever any file changes. Stored as a string, which is
     * unaffected by `cache.serializable_classes`.
     *
     * @param  Closure(): string  $build
     */
    public function rememberSitemap(DocumentCollection $documents, Closure $build): string
    {
        return $this->remember(CacheKey::sitemap($this->signature($documents)), $build);
    }

    /**
     * Cache the rendered feed XML (RSS or Atom), keyed by format + combined
     * document mtimes so it busts whenever any file changes.
     *
     * @param  Closure(): string  $build
     */
    public function rememberFeed(DocumentCollection $documents, string $format, Closure $build): string
    {
        return $this->remember(CacheKey::feed($this->signature($documents), $format), $build);
    }

    /**
     * Cache the pre-rendered search index, keyed by the combined document
     * mtimes so it busts whenever any file changes. Stored as a plain array
     * of scalars, which is unaffected by `cache.serializable_classes`.
     *
     * @param  Closure(): array<int, array{slug: string, title: string, group: string, content: string, rank: float}>  $build
     * @return array<int, array{slug: string, title: string, group: string, content: string, rank: float}>
     */
    public function rememberSearchIndex(DocumentCollection $documents, Closure $build): array
    {
        return $this->remember(CacheKey::search($this->signature($documents)), $build);
    }

    /**
     * Remove every entry this package has written.
     */
    public function flush(): void
    {
        foreach ($this->trackedKeys() as $key) {
            $this->store->forget($key);
        }

        $this->store->forget($this->indexKey());
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function documentKey(Document $document): string
    {
        return CacheKey::document($document);
    }

    /**
     * A stable hash of the collection's files and mtimes, shared by every
     * collection-wide artifact (tree, search index) so they all bust together.
     */
    private function signature(DocumentCollection $documents): string
    {
        return hash('sha256', $documents
            ->map(function (Document $doc): string {
                return $doc->relativePath . ':' . $doc->modifiedAt;
            })
            ->sort()
            ->implode('|'));
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function remember(string $key, Closure $callback)
    {
        if (! $this->enabled) {
            return $callback();
        }

        if ($this->store->has($key)) {
            return $this->store->get($key);
        }

        $value = $callback();
        $this->put($key, $value);
        $this->track($key);

        return $value;
    }

    /**
     * @param mixed $value
     */
    private function put(string $key, $value): void
    {
        if ($this->ttl === null) {
            $this->store->forever($key, $value);
        } else {
            $this->store->put($key, $value, $this->ttl);
        }
    }

    private function track(string $key): void
    {
        $keys = $this->trackedKeys();

        if (! in_array($key, $keys, true)) {
            $keys[] = $key;
            $this->store->forever($this->indexKey(), $keys);
        }
    }

    /**
     * @return array<int, string>
     */
    private function trackedKeys(): array
    {
        /** @var array<int, string> $keys */
        $keys = $this->store->get($this->indexKey(), []);

        return $keys;
    }

    private function indexKey(): string
    {
        return CacheKey::index();
    }
}

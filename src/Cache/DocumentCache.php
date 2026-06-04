<?php

declare(strict_types=1);

namespace Laradocs\Cache;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Laradocs\Documents\Document;
use Laradocs\Documents\DocumentCollection;
use Laradocs\Documents\DocumentTree;

final class DocumentCache
{
    public function __construct(
        private readonly Repository $store,
        private readonly bool $enabled = true,
        private readonly ?int $ttl = null,
        private readonly string $prefix = 'laradocs',
    ) {}

    /**
     * Cache a document's rendered HTML, keyed by file path + mtime.
     *
     * @param  Closure(): string  $render
     */
    public function rememberHtml(Document $document, Closure $render): string
    {
        $key = $this->documentKey($document);

        return $this->remember($key, $render);
    }

    /**
     * Cache the navigation tree keyed by the combined document mtimes so it
     * busts whenever any file changes. The tree is stored as a pre-serialized
     * string so consumers with Laravel's `cache.serializable_classes => false`
     * setting still receive a real DocumentTree on a hit (Laravel would
     * otherwise return __PHP_Incomplete_Class for any cached object).
     *
     * @param  DocumentCollection<int, Document>  $documents
     * @param  Closure(): DocumentTree  $build
     */
    public function rememberTree(DocumentCollection $documents, Closure $build): DocumentTree
    {
        $key = $this->prefix . ':tree:' . $this->signature($documents);

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
     * Cache the pre-rendered search index, keyed by the combined document
     * mtimes so it busts whenever any file changes. Stored as a plain array
     * of scalars, which is unaffected by `cache.serializable_classes`.
     *
     * @param  DocumentCollection<int, Document>  $documents
     * @param  Closure(): array<int, array{slug: string, title: string, group: string, content: string}>  $build
     * @return array<int, array{slug: string, title: string, group: string, content: string}>
     */
    public function rememberSearchIndex(DocumentCollection $documents, Closure $build): array
    {
        $key = $this->prefix . ':search:' . $this->signature($documents);

        return $this->remember($key, $build);
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
        return $this->prefix . ':doc:' . md5($document->path) . ':' . $document->modifiedAt;
    }

    /**
     * A stable hash of the collection's files and mtimes, shared by every
     * collection-wide artifact (tree, search index) so they all bust together.
     *
     * @param  DocumentCollection<int, Document>  $documents
     */
    private function signature(DocumentCollection $documents): string
    {
        return md5($documents
            ->map(fn (Document $doc): string => $doc->relativePath . ':' . $doc->modifiedAt)
            ->sort()
            ->implode('|'));
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function remember(string $key, Closure $callback): mixed
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

    private function put(string $key, mixed $value): void
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
        return $this->prefix . ':index';
    }
}

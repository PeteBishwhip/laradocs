<?php

declare(strict_types=1);

use Laradocs\Documents\Document;
use Laradocs\Documents\DocumentCollection;
use Laradocs\Search\Contracts\SearchEngine;
use Laradocs\Search\JsonSearchEngine;
use Laradocs\Search\ScoutSearchEngine;
use Laradocs\Search\SearchableDocument;
use Laradocs\Search\SearchIndexBuilder;
use Laradocs\Search\SearchManager;
use Laradocs\Support\Html;
use Laradocs\Tests\Fixtures\FakeScoutEngine;
use Laravel\Scout\EngineManager;

/**
 * @return array<int, array{slug: string, title: string, group: string, content: string, rank: float}>
 */
function sampleIndex(): array
{
    return [
        ['slug' => 'install', 'title' => 'Installation', 'group' => 'Guide', 'content' => 'Run composer require to install the package.', 'rank' => 1.0],
        ['slug' => 'config', 'title' => 'Configuration', 'group' => 'Guide', 'content' => 'Publish and edit the config file.', 'rank' => 1.0],
        ['slug' => 'search', 'title' => 'Search', 'group' => 'Features', 'content' => 'Full text search over every page body.', 'rank' => 1.0],
    ];
}

function stubEngine(string $name): SearchEngine
{
    return new class($name) implements SearchEngine
    {
        /**
         * @var string
         */
        private $label;
        public function __construct(string $label)
        {
            $this->label = $label;
        }

        public function search(string $query, array $index, int $limit): array
        {
            return [];
        }

        public function sync(array $index): void
        {
            // No-op: this stub only needs name() for engine-resolution tests.
        }

        public function flush(): void
        {
            // No-op: this stub only needs name() for engine-resolution tests.
        }

        public function name(): string
        {
            return $this->label;
        }
    };
}

it('flattens html to collapsed plain text', function () {
    expect(Html::toText('<p>Hello <strong>world</strong></p>   <p>again</p>'))->toBe('Hello world again')
        ->and(Html::toText('   '))->toBe('');
});

it('builds an index of visible, searchable pages in order', function () {
    $documents = new DocumentCollection([
        makeDocument('b', ['title' => 'Beta', 'order' => 2, 'group' => 'G'], 'beta body'),
        makeDocument('a', ['title' => 'Alpha', 'order' => 1], 'alpha body'),
        makeDocument('hidden', ['title' => 'Hidden', 'hidden' => true], 'nope'),
        makeDocument('private', ['title' => 'Private', 'search' => false], 'nope'),
    ]);

    $index = (new SearchIndexBuilder)->build(
        $documents,
        function (Document $document): string {
            return '<p>' . $document->markdown . '</p>';
        },
    );

    expect($index)->toHaveCount(2)
        ->and(array_column($index, 'slug'))->toBe(['a', 'b'])
        ->and($index[0])->toBe(['slug' => 'a', 'title' => 'Alpha', 'group' => '', 'content' => 'alpha body', 'rank' => 1.0])
        ->and($index[1]['group'])->toBe('G');
});

it('caps indexed content at the configured length', function () {
    $documents = new DocumentCollection([makeDocument('a', [], 'abcdefghij')]);

    $index = (new SearchIndexBuilder)->build(
        $documents,
        function (Document $document): string {
            return $document->markdown;
        },
        4,
    );

    expect($index[0]['content'])->toBe('abcd');
});

it('ranks json results with titles outweighing body and requires every term', function () {
    $engine = new JsonSearchEngine;
    $results = $engine->search('install', sampleIndex(), 10);

    expect($engine->name())->toBe('json')
        ->and(array_column($results, 'slug'))->toBe(['install']);

    // "search" appears in the Search title (weight 3) and the install body? no —
    // only the Search page, matched by title.
    expect(array_column($engine->search('search', sampleIndex(), 10), 'slug'))->toBe(['search']);

    // Multi-term AND: "config file" both live on the config page only.
    expect(array_column($engine->search('config file', sampleIndex(), 10), 'slug'))->toBe(['config']);

    // A term present nowhere excludes everything.
    expect($engine->search('nonexistent', sampleIndex(), 10))->toBe([]);

    // Empty query yields nothing.
    expect($engine->search('   ', sampleIndex(), 10))->toBe([]);
});

it('respects the json result limit and is a no-op to sync/flush', function () {
    $engine = new JsonSearchEngine;

    expect($engine->search('the', [
        ['slug' => 'a', 'title' => 'A', 'group' => '', 'content' => 'the thing', 'rank' => 1.0],
        ['slug' => 'b', 'title' => 'B', 'group' => '', 'content' => 'the other', 'rank' => 1.0],
    ], 1))->toHaveCount(1);

    $engine->sync(sampleIndex());
    $engine->flush();
});

it('exposes the scout searchable surface', function () {
    $doc = new SearchableDocument('idx', 'slug-a', 'Title', 'Body', 'Group');

    expect($doc->searchableAs())->toBe('idx')
        ->and($doc->indexableAs())->toBe('idx')
        ->and($doc->getScoutKeyName())->toBe('slug')
        ->and($doc->getScoutKey())->toBe('slug-a')
        ->and($doc->scoutMetadata())->toBe([])
        ->and($doc->toSearchableArray())->toBe([
            'slug' => 'slug-a',
            'title' => 'Title',
            'content' => 'Body',
            'group' => 'Group',
        ]);
});

it('encodes path-style slugs into engine-safe primary keys', function () {
    // Meilisearch / Algolia primary keys only allow [a-zA-Z0-9_-], so the "/"
    // in routed slugs like "guide/routing" must be encoded before indexing.
    $doc = new SearchableDocument('idx', 'guide/routing', 'Routing', 'Body', 'Guide');

    expect($doc->getScoutKey())->toBe('guide__routing')
        ->and(SearchableDocument::scoutKeyFor('guide/routing'))->toBe('guide__routing')
        ->and(SearchableDocument::scoutKeyFor('plain-slug'))->toBe('plain-slug');
});

it('maps the root index slug to a non-empty primary key', function () {
    // The root index document has an empty slug, but Meilisearch rejects an
    // empty primary key with "Document identifier `\"\"` is invalid", so the
    // empty slug must map to a fixed, engine-safe sentinel.
    $doc = new SearchableDocument('idx', '', 'Home', 'Body', '');

    expect($doc->getScoutKey())->toBe('__index__')
        ->and(SearchableDocument::scoutKeyFor(''))->toBe('__index__');
});

it('resolves the configured search engine', function () {
    $json = stubEngine('json');
    $scout = stubEngine('scout');
    $factory = function () use ($scout): SearchEngine {
        return $scout;
    };

    $forced = function (string $driver, bool $available, bool $configured) use ($factory, $json): SearchManager {
        return new SearchManager(
            $driver, $available, $configured, $factory, $json,
        );
    };

    expect($forced('json', true, true)->engine()->name())->toBe('json')
        ->and($forced('scout', true, false)->engine()->name())->toBe('scout')
        ->and($forced('scout', false, true)->engine()->name())->toBe('json')
        ->and($forced('auto', true, true)->engine()->name())->toBe('scout')
        ->and($forced('auto', true, false)->engine()->name())->toBe('json')
        ->and($forced('auto', false, true)->engine()->name())->toBe('json');

    // The resolved engine is memoised.
    $manager = $forced('json', true, true);
    expect($manager->engine())->toBe($manager->engine());
});

it('indexes and searches through a scout engine', function () {
    config()->set('scout.driver', 'fake');
    $manager = app(EngineManager::class);
    $manager->extend('fake', function (): FakeScoutEngine {
        return new FakeScoutEngine;
    });

    /** @var FakeScoutEngine $fake */
    $fake = $manager->engine('fake');
    $engine = new ScoutSearchEngine($manager, 'laradocs-test');

    $engine->sync(sampleIndex());

    expect($engine->name())->toBe('scout')
        ->and($fake->flushed)->toBe(1)
        ->and($fake->documents)->toHaveCount(3);

    $results = $engine->search('install', sampleIndex(), 10);
    expect(array_column($results, 'slug'))->toBe(['install']);

    // A key returned by the engine but absent from the supplied index is dropped.
    $partial = array_values(array_filter(sampleIndex(), function (array $e): bool {
        return $e['slug'] !== 'install';
    }));
    expect($engine->search('install', $partial, 10))->toBe([]);

    $engine->flush();
    expect($fake->flushed)->toBe(2)
        ->and($fake->documents)->toBe([]);
});

it('flushes before syncing and skips an empty scout index', function () {
    config()->set('scout.driver', 'fake');
    $manager = app(EngineManager::class);
    $manager->extend('fake', function (): FakeScoutEngine {
        return new FakeScoutEngine;
    });

    /** @var FakeScoutEngine $fake */
    $fake = $manager->engine('fake');
    $engine = new ScoutSearchEngine($manager, 'laradocs-test');

    $engine->sync([]);

    expect($fake->flushed)->toBe(1)
        ->and($fake->documents)->toBe([]);
});

<?php

declare(strict_types=1);

use Closure;
use Laradocs\Exceptions\MeilisearchIndexingException;
use Laradocs\LaradocsServiceProvider;
use Laradocs\Search\Contracts\SearchEngine;
use Laradocs\Search\ScoutSearchEngine;
use Laradocs\Search\SearchManager;
use Laradocs\Tests\Fixtures\FakeScoutEngine;
use Laravel\Scout\EngineManager;
use Meilisearch\Client as MeilisearchClient;
use Meilisearch\Contracts\TasksQuery;
use Meilisearch\Contracts\TasksResults;

const ALPHA_DOC = "---\ntitle: Alpha\n---\nbody\n";

/**
 * Point the package at a shared, in-memory Scout engine. EngineManager isn't a
 * shared singleton under Testbench, so we bind a pre-extended instance to make
 * sure the provider and the test observe the very same engine.
 */
function bindFakeScout(): FakeScoutEngine
{
    config()->set('laradocs.search.driver', 'scout');
    config()->set('scout.driver', 'fake');

    $fake = new FakeScoutEngine;
    $manager = new EngineManager(app());
    $manager->extend('fake', fn (): FakeScoutEngine => $fake);
    app()->instance(EngineManager::class, $manager);

    return $fake;
}

it('returns full-text matches with a url, group and excerpt', function () {
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n# Home\n",
        'guide/install.md' => "---\ntitle: Installation\ngroup: Guide\n---\nRun composer require to add the package to your app.\n",
    ]);

    $response = $this->getJson('/docs/_laradocs/search?q=composer')->assertOk();

    $response->assertJsonPath('results.0.slug', 'guide/install')
        ->assertJsonPath('results.0.title', 'Installation')
        ->assertJsonPath('results.0.group', 'Guide')
        ->assertJsonPath('results.0.url', url('/docs/guide/install'));

    expect($response->json('results.0.excerpt'))->toContain('composer');
});

it('links a matched landing page to the index route, not an empty show param', function () {
    // The docs root has an empty slug and lives on the index route. A hit on it
    // must resolve to route('laradocs.index'); building route('laradocs.show',
    // ['path' => '']) instead throws UrlGenerationException and 500s the request.
    $this->makeDocs([
        '_index.md' => "---\ntitle: Home\n---\n# Home\nRun an artisan command here.\n",
    ]);

    $response = $this->getJson('/docs/_laradocs/search?q=command')->assertOk();

    $response->assertJsonPath('results.0.slug', '')
        ->assertJsonPath('results.0.url', url('/docs'));
});

it('returns a humanised breadcrumb derived from the slug and group', function () {
    $this->makeDocs([
        // Ancestor segment + group: the section name supersedes the path.
        'guide/install.md' => "---\ntitle: Installation\ngroup: Guide\n---\nUnique aardvark term.\n",
        // Deeper nesting: each ancestor past the section is humanised.
        'guide/advanced/routing.md' => "---\ntitle: Routing\ngroup: Guide\n---\nUnique boomerang term.\n",
        // Ancestor segment, no group: humanise the slug segment itself.
        'api/auth.md' => "---\ntitle: Auth\n---\nUnique crocodile term.\n",
        // Top-level page with a group: collapses to just the group.
        'intro.md' => "---\ntitle: Intro\ngroup: Basics\n---\nUnique dingo term.\n",
        // Top-level page with neither ancestors nor a group: empty trail.
        'overview.md' => "---\ntitle: Overview\n---\nUnique elephant term.\n",
    ]);

    $breadcrumb = fn (string $term): mixed => $this->getJson("/docs/_laradocs/search?q={$term}")
        ->assertOk()
        ->json('results.0.breadcrumb');

    expect($breadcrumb('aardvark'))->toBe(['Guide'])
        ->and($breadcrumb('boomerang'))->toBe(['Guide', 'Advanced'])
        ->and($breadcrumb('crocodile'))->toBe(['Api'])
        ->and($breadcrumb('dingo'))->toBe(['Basics'])
        ->and($breadcrumb('elephant'))->toBe([]);
});

it('builds an excerpt around the matched term with ellipses', function () {
    $body = str_repeat('lorem ipsum ', 40) . 'needle ' . str_repeat('dolor sit ', 40);

    $this->makeDocs([
        'long.md' => "---\ntitle: Long\n---\n{$body}\n",
    ]);

    $excerpt = $this->getJson('/docs/_laradocs/search?q=needle')->assertOk()->json('results.0.excerpt');

    expect($excerpt)->toContain('needle')
        ->and($excerpt)->toStartWith('…')
        ->and($excerpt)->toEndWith('…');
});

it('excerpts from the start without ellipses when the term leads short content', function () {
    $this->makeDocs([
        'lead.md' => "---\ntitle: Lead\n---\nneedlestart trailing words\n",
    ]);

    $excerpt = $this->getJson('/docs/_laradocs/search?q=needlestart')->assertOk()->json('results.0.excerpt');

    expect($excerpt)->toBe('needlestart trailing words');
});

it('falls back to a leading snippet when the match is in the title only', function () {
    $this->makeDocs([
        'titled.md' => "---\ntitle: Keyworded Heading\n---\nThis body never mentions the term itself.\n",
    ]);

    $excerpt = $this->getJson('/docs/_laradocs/search?q=keyworded')->assertOk()->json('results.0.excerpt');

    expect($excerpt)->toBe('This body never mentions the term itself.');
});

it('returns an empty excerpt for a body-less page matched by title', function () {
    $this->makeDocs([
        'blank.md' => "---\ntitle: Emptyword\n---\n",
    ]);

    $this->getJson('/docs/_laradocs/search?q=emptyword')
        ->assertOk()
        ->assertJsonPath('results.0.slug', 'blank')
        ->assertJsonPath('results.0.excerpt', '');
});

it('returns nothing for queries shorter than the minimum', function () {
    $this->makeDocs(['a.md' => ALPHA_DOC]);

    $this->getJson('/docs/_laradocs/search?q=a')
        ->assertOk()
        ->assertExactJson(['results' => []]);
});

it('ignores a non-string query parameter', function () {
    $this->makeDocs(['a.md' => ALPHA_DOC]);

    $this->getJson('/docs/_laradocs/search?q[]=x')
        ->assertOk()
        ->assertExactJson(['results' => []]);
});

it('404s the search endpoint when search is disabled', function () {
    config()->set('laradocs.ui.search.enabled', false);
    $this->makeDocs(['a.md' => ALPHA_DOC]);

    $this->getJson('/docs/_laradocs/search?q=alpha')->assertNotFound();
});

it('laradocs:index reports the indexed page count and engine', function () {
    $this->makeDocs(['a.md' => ALPHA_DOC, 'b.md' => "---\ntitle: Beta\n---\nbody\n"]);

    $this->artisan('laradocs:index')
        ->expectsOutputToContain('Indexed 2 page(s) for search (json engine).')
        ->assertSuccessful();
});

it('laradocs:index --docs-version rebuilds only the requested version', function () {
    config()->set('laradocs.versions.enabled', true);
    config()->set('laradocs.versions.available', null);
    $this->makeDocs([
        'v1/a.md' => ALPHA_DOC,
        'v2/a.md' => ALPHA_DOC,
        'v2/b.md' => "---\ntitle: Beta\n---\nbody\n",
    ]);

    $this->artisan('laradocs:index', ['--docs-version' => 'v1'])
        ->expectsOutputToContain('Indexed 1 page(s) for search (json engine).')
        ->assertSuccessful();
});

it('laradocs:index aborts on an unknown --docs-version', function () {
    config()->set('laradocs.versions.enabled', true);
    config()->set('laradocs.versions.available', null);
    $this->makeDocs(['v1/a.md' => ALPHA_DOC]);

    $this->artisan('laradocs:index', ['--docs-version' => 'v9'])
        ->expectsOutputToContain('Unknown version "v9".')
        ->assertFailed();
});

it('laradocs:index rebuilds every detected version in sequence', function () {
    config()->set('laradocs.versions.enabled', true);
    config()->set('laradocs.versions.available', null);
    $this->makeDocs([
        'v1/a.md' => ALPHA_DOC,
        'v2/a.md' => ALPHA_DOC,
        'v2/b.md' => "---\ntitle: Beta\n---\nbody\n",
    ]);

    $this->artisan('laradocs:index')
        ->expectsOutputToContain('Rebuilding the search index for version v1.')
        ->expectsOutputToContain('Rebuilding the search index for version v2.')
        ->expectsOutputToContain('Indexed 1 page(s) for search (json engine).')
        ->expectsOutputToContain('Indexed 2 page(s) for search (json engine).')
        ->assertSuccessful();
});

it('laradocs:cache also rebuilds the search index', function () {
    config()->set('laradocs.cache.enabled', true);
    config()->set('laradocs.cache.store', 'array');
    $this->makeDocs(['a.md' => ALPHA_DOC]);

    $this->artisan('laradocs:cache')
        ->expectsOutputToContain('Cached 1')
        ->expectsOutputToContain('Indexed 1')
        ->assertSuccessful();
});

it('laradocs:clear flushes the search engine', function () {
    $fake = bindFakeScout();
    $this->makeDocs(['a.md' => ALPHA_DOC]);

    $this->artisan('laradocs:clear')->assertSuccessful();

    expect($fake->flushed)->toBeGreaterThan(0);
});

it('laradocs:clear reports a search index flush failure without failing', function () {
    $this->makeDocs(['a.md' => ALPHA_DOC]);

    app()->bind(SearchEngine::class, fn (): SearchEngine => new class implements SearchEngine
    {
        public function search(string $query, array $index, int $limit): array
        {
            return [];
        }

        public function sync(array $index): void
        {
            // No-op: this stub only exercises the flush() failure path.
        }

        public function flush(): void
        {
            throw new RuntimeException('index unreachable');
        }

        public function name(): string
        {
            return 'broken';
        }
    });

    $this->artisan('laradocs:clear')
        ->expectsOutputToContain('Failed to flush the search index (broken engine)')
        ->expectsOutputToContain('index unreachable')
        ->assertSuccessful();
});

it('searches through the configured scout engine end to end', function () {
    bindFakeScout();
    $this->makeDocs([
        'guide/install.md' => "---\ntitle: Installation\ngroup: Guide\n---\nRun composer require to install.\n",
        'guide/config.md' => "---\ntitle: Configuration\ngroup: Guide\n---\nPublish the config file.\n",
    ]);

    $this->artisan('laradocs:index')
        ->expectsOutputToContain('scout engine')
        ->assertSuccessful();

    $this->getJson('/docs/_laradocs/search?q=composer')
        ->assertOk()
        ->assertJsonPath('results.0.slug', 'guide/install');
});

it('scoutIsConfigured requires a real intent signal, not just the algolia default', function () {
    // Scout absent / no driver set at all.
    config()->set('scout.driver', null);
    expect(LaradocsServiceProvider::scoutIsConfigured())->toBeFalse();

    // Scout merges 'algolia' as the package default whenever it's installed —
    // by itself that doesn't mean the host has configured anything.
    config()->set('scout.driver', 'algolia');
    config()->set('scout.algolia.id', '');
    expect(LaradocsServiceProvider::scoutIsConfigured())->toBeFalse();

    // Algolia with credentials counts as intent.
    config()->set('scout.algolia.id', 'app-xyz');
    expect(LaradocsServiceProvider::scoutIsConfigured())->toBeTrue();

    // Any non-default driver is itself a signal — the user picked it.
    config()->set('scout.driver', 'meilisearch');
    expect(LaradocsServiceProvider::scoutIsConfigured())->toBeTrue();
});

it('auto driver falls back to json when scout looks like the unconfigured default', function () {
    config()->set('laradocs.search.driver', 'auto');
    config()->set('scout.driver', 'algolia');
    config()->set('scout.algolia.id', '');

    app()->forgetInstance(SearchManager::class);

    expect(app(SearchManager::class)->engine()->name())->toBe('json');
});

it('auto driver picks scout when a non-default driver is configured', function () {
    config()->set('laradocs.search.driver', 'auto');
    config()->set('scout.driver', 'fake');

    $manager = new EngineManager(app());
    $manager->extend('fake', fn (): FakeScoutEngine => new FakeScoutEngine);
    app()->instance(EngineManager::class, $manager);
    app()->forgetInstance(SearchManager::class);

    expect(app(SearchManager::class)->engine()->name())->toBe('scout');
});

it('laradocs:index surfaces engine failures as a non-zero exit', function () {
    $this->makeDocs(['a.md' => ALPHA_DOC]);

    app()->bind(SearchEngine::class, fn (): SearchEngine => new class implements SearchEngine
    {
        public function search(string $query, array $index, int $limit): array
        {
            return [];
        }

        public function sync(array $index): void
        {
            throw new RuntimeException('engine unreachable');
        }

        public function flush(): void
        {
            // No-op: this stub only exercises the sync() failure path.
        }

        public function name(): string
        {
            return 'broken';
        }
    });

    $this->artisan('laradocs:index')
        ->expectsOutputToContain('Failed to index')
        ->expectsOutputToContain('engine unreachable')
        ->assertFailed();
});

/**
 * Bind a Scout driver that looks like Meilisearch to ScoutSearchEngine — its
 * `meilisearch` property holds a real `Meilisearch\Client` subclass that
 * overrides `getTasks` / `waitForTask` with in-memory implementations. Using
 * the real base class (with its real `?TasksQuery` / `TasksResults`
 * signatures) is what makes these fakes catch SDK-contract regressions — e.g.
 * accidentally passing a raw array to getTasks() — before they reach prod.
 */
function bindFakeMeilisearchScout(?Closure $onUpdate = null): MeilisearchClient
{
    $client = new class('http://localhost') extends MeilisearchClient
    {
        /** @var array<int, array<string, mixed>> */
        public array $taskLog = [];

        public function getTasks(?TasksQuery $options = null): TasksResults
        {
            $query = $options?->toArray() ?? [];
            $statusesCsv = is_string($query['statuses'] ?? null) ? $query['statuses'] : '';
            $statuses = $statusesCsv === '' ? [] : explode(',', $statusesCsv);

            $matching = array_values(array_filter(
                $this->taskLog,
                fn (array $task): bool => $statuses === [] || in_array($task['status'] ?? null, $statuses, true),
            ));

            return new TasksResults(['results' => $matching]);
        }

        public function waitForTask($uid, int $timeoutInMs = 5000, int $intervalInMs = 50): array
        {
            foreach ($this->taskLog as &$task) {
                if (($task['uid'] ?? null) === $uid && ($task['status'] ?? null) !== 'failed') {
                    $task['status'] = 'succeeded';
                }
            }
            unset($task);

            return [];
        }
    };

    // Scout engine that mirrors MeilisearchEngine's protected `meilisearch`
    // property so ScoutSearchEngine's ReflectionProperty lookup resolves it.
    // The optional $onUpdate hook lets a test simulate Meilisearch queuing
    // indexing tasks when documents are pushed.
    $scoutEngine = new class($client, $onUpdate) extends FakeScoutEngine
    {
        public function __construct(
            protected MeilisearchClient $meilisearch,
            private ?Closure $onUpdate,
        ) {}

        public function update($models): void
        {
            parent::update($models);

            if ($this->onUpdate !== null) {
                ($this->onUpdate)($this->meilisearch);
            }
        }
    };

    config()->set('laradocs.search.driver', 'scout');
    config()->set('scout.driver', 'fake-meili');

    $manager = new EngineManager(app());
    $manager->extend('fake-meili', fn (): object => $scoutEngine);
    app()->instance(EngineManager::class, $manager);

    return $client;
}

it('ScoutSearchEngine surfaces failed Meilisearch tasks queued during sync', function () {
    $client = bindFakeMeilisearchScout();

    // Baseline lookup returns the highest existing task UID; anything > 10
    // counts as part of "this sync" for the failure check.
    $client->taskLog = [['uid' => 10, 'status' => 'succeeded']];

    // Simulate Meilisearch accepting addDocuments but later failing the task
    // (the exact scenario from the slug primary-key bug).
    $client->taskLog[] = ['uid' => 11, 'status' => 'failed', 'type' => 'documentAdditionOrUpdate', 'error' => ['message' => 'invalid primary key']];

    $engine = new ScoutSearchEngine(app(EngineManager::class), 'laradocs-test');

    expect(fn () => $engine->sync([
        ['slug' => 'a', 'title' => 'A', 'group' => '', 'content' => 'body'],
    ]))
        ->toThrow(MeilisearchIndexingException::class, 'invalid primary key');
});

it('laradocs:index fails when Meilisearch rejects a queued indexing task', function () {
    $this->makeDocs(['a.md' => ALPHA_DOC]);

    $client = bindFakeMeilisearchScout();
    $client->taskLog = [['uid' => 10, 'status' => 'succeeded']];
    $client->taskLog[] = ['uid' => 11, 'status' => 'failed', 'type' => 'documentAdditionOrUpdate', 'error' => ['message' => 'invalid primary key']];

    $this->artisan('laradocs:index')
        ->expectsOutputToContain('Failed to index')
        ->expectsOutputToContain('invalid primary key')
        ->assertFailed();
});

it('ScoutSearchEngine awaits pending tasks and reports a failure with no error message', function () {
    // Empty taskLog exercises the "no prior tasks" baseline branch in
    // latestTaskUid(). The update hook then queues an enqueued task (waited
    // on by waitForPendingTasks) and a failed task with no `error.message`,
    // which trips the "unknown error" fallback in failedTaskMessage().
    $client = bindFakeMeilisearchScout(function (MeilisearchClient $client): void {
        $client->taskLog[] = ['uid' => 1, 'status' => 'enqueued'];
        $client->taskLog[] = ['uid' => 2, 'status' => 'failed', 'type' => 'documentAdditionOrUpdate'];
    });

    $engine = new ScoutSearchEngine(app(EngineManager::class), 'laradocs-test');

    expect(fn () => $engine->sync([
        ['slug' => 'a', 'title' => 'A', 'group' => '', 'content' => 'body'],
    ]))
        ->toThrow(MeilisearchIndexingException::class, 'unknown error [documentAdditionOrUpdate]');

    // The enqueued task should have been waited on and marked succeeded.
    expect($client->taskLog[0]['status'])->toBe('succeeded');
});

it('laradocs:index succeeds against Meilisearch when no tasks fail', function () {
    $this->makeDocs(['a.md' => ALPHA_DOC]);

    $client = bindFakeMeilisearchScout();
    $client->taskLog = [['uid' => 10, 'status' => 'succeeded']];

    $this->artisan('laradocs:index')
        ->expectsOutputToContain('Indexed 1 page(s) for search (scout engine).')
        ->assertSuccessful();
});

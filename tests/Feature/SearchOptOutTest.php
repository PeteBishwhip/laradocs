<?php

declare(strict_types=1);

// ─── Per-page opt-out (search: false) ────────────────────────────────────────

it('search: false excludes a page from the index', function () {
    $this->makeDocs([
        'guide/intro.md' => "---\ntitle: Intro\n---\nComposer install instructions.\n",
        'guide/secret.md' => "---\ntitle: Secret\nsearch: false\n---\nComposer secret instructions.\n",
    ]);

    $response = $this->getJson('/docs/_laradocs/search?q=composer')->assertOk();

    $slugs = array_column($response->json('results'), 'slug');

    expect($slugs)->toContain('guide/intro')
        ->and($slugs)->not->toContain('guide/secret');
});

it('search: false keeps the page URL reachable', function () {
    $this->makeDocs([
        'guide/secret.md' => "---\ntitle: Secret\nsearch: false\n---\nBody.\n",
    ]);

    $this->get('/docs/guide/secret')->assertOk()->assertSee('Secret');
});

it('search: true is the default and includes the page', function () {
    $this->makeDocs([
        'included.md' => "---\ntitle: Included\n---\nComposer install.\n",
    ]);

    $response = $this->getJson('/docs/_laradocs/search?q=composer')->assertOk();

    expect(array_column($response->json('results'), 'slug'))->toContain('included');
});

// ─── Config-level exclude list ────────────────────────────────────────────────

it('config exclude pattern removes matching slugs from the index', function () {
    config()->set('laradocs.search.exclude', ['internal/*']);

    $this->makeDocs([
        'guide/intro.md' => "---\ntitle: Intro\n---\nComposer install.\n",
        'internal/notes.md' => "---\ntitle: Notes\n---\nComposer notes.\n",
    ]);

    $response = $this->getJson('/docs/_laradocs/search?q=composer')->assertOk();

    $slugs = array_column($response->json('results'), 'slug');

    expect($slugs)->toContain('guide/intro')
        ->and($slugs)->not->toContain('internal/notes');
});

it('config exclude pattern does not affect page reachability', function () {
    config()->set('laradocs.search.exclude', ['internal/*']);

    $this->makeDocs([
        'internal/notes.md' => "---\ntitle: Notes\n---\nBody.\n",
    ]);

    $this->get('/docs/internal/notes')->assertOk();
});

it('multiple exclude patterns are all applied', function () {
    config()->set('laradocs.search.exclude', ['drafts/*', 'internal/*']);

    $this->makeDocs([
        'public.md' => "---\ntitle: Public\n---\nComposer term.\n",
        'drafts/wip.md' => "---\ntitle: WIP\n---\nComposer term.\n",
        'internal/notes.md' => "---\ntitle: Notes\n---\nComposer term.\n",
    ]);

    $response = $this->getJson('/docs/_laradocs/search?q=composer')->assertOk();

    $slugs = array_column($response->json('results'), 'slug');

    expect($slugs)->toContain('public')
        ->and($slugs)->not->toContain('drafts/wip')
        ->and($slugs)->not->toContain('internal/notes');
});

it('exact slug exclude pattern works without a wildcard', function () {
    config()->set('laradocs.search.exclude', ['changelog']);

    $this->makeDocs([
        'changelog.md' => "---\ntitle: Changelog\n---\nRelease notes.\n",
        'guide.md' => "---\ntitle: Guide\n---\nRelease info.\n",
    ]);

    $response = $this->getJson('/docs/_laradocs/search?q=release')->assertOk();

    $slugs = array_column($response->json('results'), 'slug');

    expect($slugs)->toContain('guide')
        ->and($slugs)->not->toContain('changelog');
});

// ─── Config-level include allow-list ─────────────────────────────────────────

it('config include allow-list restricts index to matching slugs only', function () {
    config()->set('laradocs.search.include', ['guide/*']);

    $this->makeDocs([
        'guide/intro.md' => "---\ntitle: Intro\n---\nComposer term.\n",
        'reference/api.md' => "---\ntitle: API\n---\nComposer term.\n",
    ]);

    $response = $this->getJson('/docs/_laradocs/search?q=composer')->assertOk();

    $slugs = array_column($response->json('results'), 'slug');

    expect($slugs)->toContain('guide/intro')
        ->and($slugs)->not->toContain('reference/api');
});

it('include allow-list with multiple patterns allows all matching slugs', function () {
    config()->set('laradocs.search.include', ['guide/*', 'reference/*']);

    $this->makeDocs([
        'guide/intro.md' => "---\ntitle: Intro\n---\nComposer term.\n",
        'reference/api.md' => "---\ntitle: API\n---\nComposer term.\n",
        'internal/notes.md' => "---\ntitle: Notes\n---\nComposer term.\n",
    ]);

    $response = $this->getJson('/docs/_laradocs/search?q=composer')->assertOk();

    $slugs = array_column($response->json('results'), 'slug');

    expect($slugs)->toContain('guide/intro')
        ->and($slugs)->toContain('reference/api')
        ->and($slugs)->not->toContain('internal/notes');
});

// ─── Interaction: per-page + config lists ────────────────────────────────────

it('search: false takes priority over a matching include pattern', function () {
    config()->set('laradocs.search.include', ['guide/*']);

    $this->makeDocs([
        'guide/intro.md' => "---\ntitle: Intro\n---\nComposer term.\n",
        'guide/secret.md' => "---\ntitle: Secret\nsearch: false\n---\nComposer term.\n",
    ]);

    $response = $this->getJson('/docs/_laradocs/search?q=composer')->assertOk();

    $slugs = array_column($response->json('results'), 'slug');

    expect($slugs)->toContain('guide/intro')
        ->and($slugs)->not->toContain('guide/secret');
});

it('exclude takes priority when both exclude and include match the same slug', function () {
    config()->set('laradocs.search.exclude', ['guide/secret']);
    config()->set('laradocs.search.include', ['guide/*']);

    $this->makeDocs([
        'guide/intro.md' => "---\ntitle: Intro\n---\nComposer term.\n",
        'guide/secret.md' => "---\ntitle: Secret\n---\nComposer term.\n",
    ]);

    $response = $this->getJson('/docs/_laradocs/search?q=composer')->assertOk();

    $slugs = array_column($response->json('results'), 'slug');

    expect($slugs)->toContain('guide/intro')
        ->and($slugs)->not->toContain('guide/secret');
});

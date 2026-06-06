<?php

declare(strict_types=1);

// Helpers — re-run a search and return the ordered list of slugs.
function searchSlugs(mixed $test, string $query): array
{
    return array_column(
        $test->getJson("/docs/_laradocs/search?q={$query}")->assertOk()->json('results'),
        'slug'
    );
}

// ─── Per-page search_rank front-matter ───────────────────────────────────────

it('search_rank: 2.0 boosts a page above an equally relevant result', function () {
    $this->makeDocs([
        'normal.md' => "---\ntitle: Normal\n---\nComposer install.\n",
        'boosted.md' => "---\ntitle: Boosted\nsearch_rank: 2.0\n---\nComposer install.\n",
    ]);

    $slugs = searchSlugs($this, 'composer');

    expect(array_search('boosted', $slugs))->toBeLessThan(array_search('normal', $slugs));
});

it('search_rank: 0.5 demotes a page below an equally relevant result', function () {
    $this->makeDocs([
        'normal.md' => "---\ntitle: Normal\n---\nComposer install.\n",
        'demoted.md' => "---\ntitle: Demoted\nsearch_rank: 0.5\n---\nComposer install.\n",
    ]);

    $slugs = searchSlugs($this, 'composer');

    expect(array_search('demoted', $slugs))->toBeGreaterThan(array_search('normal', $slugs));
});

it('search_rank: 0 sends a page to the bottom', function () {
    $this->makeDocs([
        'other.md' => "---\ntitle: Other\n---\nComposer term.\n",
        'zeroed.md' => "---\ntitle: Zeroed\nsearch_rank: 0\n---\nComposer term.\n",
    ]);

    $slugs = searchSlugs($this, 'composer');

    expect(array_search('zeroed', $slugs))->toBe(count($slugs) - 1);
});

it('search_rank defaults to 1.0 and has no effect on ordering', function () {
    $this->makeDocs([
        'a.md' => "---\ntitle: Alpha\n---\nComposer install.\n",
    ]);

    $results = $this->getJson('/docs/_laradocs/search?q=composer')->assertOk()->json('results');

    expect($results)->toHaveCount(1);
});

// ─── Config-level search.rank patterns ───────────────────────────────────────

it('config rank pattern boosts matching pages above unranked equals', function () {
    config()->set('laradocs.search.rank', ['guide/*' => 3.0]);

    $this->makeDocs([
        'other.md' => "---\ntitle: Other\n---\nComposer install.\n",
        'guide/intro.md' => "---\ntitle: Guide\n---\nComposer install.\n",
    ]);

    $slugs = searchSlugs($this, 'composer');

    expect(array_search('guide/intro', $slugs))->toBeLessThan(array_search('other', $slugs));
});

it('config rank pattern demotes matching pages below unranked equals', function () {
    config()->set('laradocs.search.rank', ['changelog' => 0.25]);

    $this->makeDocs([
        'guide.md' => "---\ntitle: Guide\n---\nRelease notes.\n",
        'changelog.md' => "---\ntitle: Changelog\n---\nRelease notes.\n",
    ]);

    $slugs = searchSlugs($this, 'release');

    expect(array_search('changelog', $slugs))->toBeGreaterThan(array_search('guide', $slugs));
});

it('first matching config pattern wins when multiple patterns match', function () {
    config()->set('laradocs.search.rank', [
        'guide/intro' => 5.0,   // more specific — checked first
        'guide/*' => 0.1,
    ]);

    $this->makeDocs([
        'guide/intro.md' => "---\ntitle: Intro\n---\nComposer install.\n",
        'guide/advanced.md' => "---\ntitle: Advanced\n---\nComposer install.\n",
    ]);

    $slugs = searchSlugs($this, 'composer');

    // intro matches the 5.0 pattern first → ranked above advanced (0.1)
    expect(array_search('guide/intro', $slugs))->toBeLessThan(array_search('guide/advanced', $slugs));
});

// ─── Per-page and config rank combine ────────────────────────────────────────

it('per-page and config rank multiply together', function () {
    config()->set('laradocs.search.rank', ['guide/*' => 2.0]);

    $this->makeDocs([
        'other.md' => "---\ntitle: Other\n---\nComposer install.\n",
        'guide/intro.md' => "---\ntitle: Intro\nsearch_rank: 3.0\n---\nComposer install.\n",
    ]);

    // guide/intro final rank = 2.0 * 3.0 = 6.0 vs other = 1.0
    $slugs = searchSlugs($this, 'composer');

    expect(array_search('guide/intro', $slugs))->toBeLessThan(array_search('other', $slugs));
});

it('per-page rank of 0 beats a high config pattern rank', function () {
    config()->set('laradocs.search.rank', ['guide/*' => 10.0]);

    $this->makeDocs([
        'other.md' => "---\ntitle: Other\n---\nComposer install.\n",
        'guide/zeroed.md' => "---\ntitle: Zeroed\nsearch_rank: 0\n---\nComposer install.\n",
    ]);

    $slugs = searchSlugs($this, 'composer');

    // 10.0 * 0 = 0 → zeroed goes to the bottom
    expect(array_search('guide/zeroed', $slugs))->toBeGreaterThan(array_search('other', $slugs));
});

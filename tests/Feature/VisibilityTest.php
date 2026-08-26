<?php

declare(strict_types=1);

use Laradocs\Contracts\DocumentLoader;
use Laradocs\Contracts\DocumentVisibility;
use Laradocs\Documents\Document;
use Laradocs\Documents\DocumentCollection;
use Laradocs\Laradocs;
use Laradocs\Loaders\VisibilityLoader;

/**
 * A rule that hides anything under a "private" directory, and counts how often
 * it was asked, so the tests can say something about cost as well as effect.
 */
function hidePrivate(): object
{
    $rule = new class implements DocumentVisibility
    {
        public int $calls = 0;

        public function filter(DocumentCollection $documents): DocumentCollection
        {
            $this->calls++;

            return $documents
                ->reject(fn (Document $document): bool => str_starts_with($document->slug, 'private'))
                ->values();
        }
    };

    app()->instance(DocumentVisibility::class, $rule);

    return $rule;
}

function withDocs(): void
{
    test()->makeDocs([
        'index.md' => "---\ntitle: Home\n---\n\nHome.\n",
        'public-guide.md' => "---\ntitle: Public\n---\n\nPublic body.\n",
        'private/secret.md' => "---\ntitle: Secret\n---\n\nSecret body.\n",
    ]);
}

it('reads everything when no rule is bound', function () {
    withDocs();

    expect(app(Laradocs::class)->all())->toHaveCount(3);
});

it('hides documents the rule rejects', function () {
    hidePrivate();
    withDocs();

    $slugs = app(Laradocs::class)->all()->pluck('slug')->all();

    expect($slugs)->not->toContain('private/secret')
        ->and($slugs)->toContain('public-guide');
});

it('hides them from a direct url as well as from the navigation', function () {
    hidePrivate();
    withDocs();

    $this->get('/docs/public-guide')->assertOk();
    $this->get('/docs/private/secret')->assertNotFound();
});

it('keeps them out of the tree, the search index and the sitemap', function () {
    hidePrivate();
    withDocs();

    $laradocs = app(Laradocs::class);

    $slugs = collect($laradocs->searchIndex())->pluck('slug')->all();

    expect($slugs)->not->toContain('private/secret');
    expect($laradocs->sitemap())->not->toContain('private/secret');
    expect((string) json_encode($laradocs->tree()->navigation()))->not->toContain('private/secret');
});

it('reads the documents once, however often they are asked for', function () {
    $rule = hidePrivate();
    withDocs();

    $laradocs = app(Laradocs::class);

    $laradocs->all();
    $laradocs->tree();
    $laradocs->searchIndex();
    $laradocs->find('public-guide');

    // The files are read once. The rule is consulted each time, because only it
    // knows whether its answer would have changed.
    expect($rule->calls)->toBeGreaterThan(1);
});

it('reads everything again inside withoutVisibility', function () {
    hidePrivate();
    withDocs();

    $laradocs = app(Laradocs::class);

    $all = $laradocs->withoutVisibility(fn (): DocumentCollection => $laradocs->all());

    expect($all)->toHaveCount(3)
        // And the rule is back in force afterwards.
        ->and($laradocs->all())->toHaveCount(2);
});

it('runs the callback unchanged when no rule is bound', function () {
    withDocs();

    expect(app(Laradocs::class)->withoutVisibility(fn (): int => 42))->toBe(42);
});

it('restores the rule even when the callback throws', function () {
    hidePrivate();
    withDocs();

    $laradocs = app(Laradocs::class);

    expect(fn () => $laradocs->withoutVisibility(fn () => throw new RuntimeException('nope')))
        ->toThrow(RuntimeException::class);

    expect($laradocs->all())->toHaveCount(2);
});

it('leaves the loader untouched when nothing is bound', function () {
    withDocs();

    expect(app(DocumentLoader::class))->not->toBeInstanceOf(VisibilityLoader::class);
});

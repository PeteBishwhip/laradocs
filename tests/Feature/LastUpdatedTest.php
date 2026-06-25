<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Laradocs\Documents\Document;
use Laradocs\Facades\Laradocs;
use Laradocs\Metadata\Metadata;

// ── front_matter (default) ─────────────────────────────────────────────────────

it('returns front-matter updated_at formatted with locale.date_format by default', function () {
    $doc = makeDocument('intro', ['updated_at' => '2026-03-01']);

    expect(Laradocs::resolveLastUpdated($doc))->toBe('1st March 2026');
});

it('returns null when no front-matter date is set and source is front_matter', function () {
    config()->set('laradocs.ui.last_updated_source', 'front_matter');
    $doc = makeDocument('intro');

    expect(Laradocs::resolveLastUpdated($doc))->toBeNull();
});

// ── mtime ──────────────────────────────────────────────────────────────────────

it('returns a formatted mtime when source is mtime', function () {
    config()->set('laradocs.ui.last_updated_source', 'mtime');
    $doc = makeDocument('intro');

    // makeDocument sets modifiedAt = 1700000000 → formatted with the default locale.date_format
    $expected = CarbonImmutable::createFromTimestamp(1700000000)->format(
        config('laradocs.locale.date_format', 'jS F Y')
    );
    expect(Laradocs::resolveLastUpdated($doc))->toBe($expected);
});

it('ignores front-matter updated_at when source is mtime', function () {
    config()->set('laradocs.ui.last_updated_source', 'mtime');
    $doc = makeDocument('intro', ['updated_at' => '2026-03-01']);

    $expected = CarbonImmutable::createFromTimestamp(1700000000)->format(
        config('laradocs.locale.date_format', 'jS F Y')
    );
    expect(Laradocs::resolveLastUpdated($doc))->toBe($expected);
});

it('returns null from mtime when modifiedAt is zero', function () {
    config()->set('laradocs.ui.last_updated_source', 'mtime');
    $doc = new Document(
        path: '/virtual/intro.md',
        relativePath: 'intro.md',
        slug: 'intro',
        metadata: Metadata::fromArray([]),
        markdown: '',
        modifiedAt: 0,
    );

    expect(Laradocs::resolveLastUpdated($doc))->toBeNull();
});

// ── front_matter_or_mtime ──────────────────────────────────────────────────────

it('prefers front-matter over mtime when source is front_matter_or_mtime', function () {
    config()->set('laradocs.ui.last_updated_source', 'front_matter_or_mtime');
    $doc = makeDocument('intro', ['updated_at' => '2026-03-01']);

    expect(Laradocs::resolveLastUpdated($doc))->toBe('1st March 2026');
});

it('falls back to mtime when front-matter is absent and source is front_matter_or_mtime', function () {
    config()->set('laradocs.ui.last_updated_source', 'front_matter_or_mtime');
    $doc = makeDocument('intro');

    $expected = CarbonImmutable::createFromTimestamp(1700000000)->format(
        config('laradocs.locale.date_format', 'jS F Y')
    );
    expect(Laradocs::resolveLastUpdated($doc))->toBe($expected);
});

// ── custom closure ─────────────────────────────────────────────────────────────

it('uses a registered closure over any config source', function () {
    config()->set('laradocs.ui.last_updated_source', 'front_matter');

    Laradocs::getLastUpdatedUsing(fn (Document $doc) => 'custom-' . $doc->slug);

    $doc = makeDocument('intro', ['updated_at' => '2026-03-01']);

    expect(Laradocs::resolveLastUpdated($doc))->toBe('custom-intro');
});

it('treats an empty string returned by the closure as null', function () {
    Laradocs::getLastUpdatedUsing(fn (Document $doc) => '');

    expect(Laradocs::resolveLastUpdated(makeDocument('intro')))->toBeNull();
});

it('treats null returned by the closure as null', function () {
    Laradocs::getLastUpdatedUsing(fn (Document $doc) => null);

    expect(Laradocs::resolveLastUpdated(makeDocument('intro')))->toBeNull();
});

it('reverts to config-driven resolution when closure is cleared', function () {
    config()->set('laradocs.ui.last_updated_source', 'front_matter');

    Laradocs::getLastUpdatedUsing(fn (Document $doc) => 'custom');
    Laradocs::getLastUpdatedUsing(null);

    $doc = makeDocument('intro', ['updated_at' => '2026-03-01']);

    expect(Laradocs::resolveLastUpdated($doc))->toBe('1st March 2026');
});

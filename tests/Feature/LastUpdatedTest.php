<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Laradocs\Documents\Document;
use Laradocs\Metadata\Metadata;
use Laradocs\Support\LastUpdatedConfig;

afterEach(fn () => LastUpdatedConfig::setResolver(null));

// ── front_matter (default) ─────────────────────────────────────────────────────

it('returns front-matter updated_at formatted with locale.date_format by default', function () {
    $doc = makeDocument('intro', ['updated_at' => '2026-03-01']);

    expect(LastUpdatedConfig::resolve($doc))->toBe('1st March 2026');
});

it('returns null when no front-matter date is set and source is front_matter', function () {
    config()->set('laradocs.ui.last_updated_source', 'front_matter');
    $doc = makeDocument('intro');

    expect(LastUpdatedConfig::resolve($doc))->toBeNull();
});

// ── mtime ──────────────────────────────────────────────────────────────────────

it('returns a formatted mtime when source is mtime', function () {
    config()->set('laradocs.ui.last_updated_source', 'mtime');
    $doc = makeDocument('intro');

    // makeDocument sets modifiedAt = 1700000000 → formatted with the default locale.date_format
    $expected = CarbonImmutable::createFromTimestamp(1700000000)->format(
        config('laradocs.locale.date_format', 'jS F Y')
    );
    expect(LastUpdatedConfig::resolve($doc))->toBe($expected);
});

it('ignores front-matter updated_at when source is mtime', function () {
    config()->set('laradocs.ui.last_updated_source', 'mtime');
    $doc = makeDocument('intro', ['updated_at' => '2026-03-01']);

    $expected = CarbonImmutable::createFromTimestamp(1700000000)->format(
        config('laradocs.locale.date_format', 'jS F Y')
    );
    expect(LastUpdatedConfig::resolve($doc))->toBe($expected);
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

    expect(LastUpdatedConfig::resolve($doc))->toBeNull();
});

// ── front_matter_or_mtime ──────────────────────────────────────────────────────

it('prefers front-matter over mtime when source is front_matter_or_mtime', function () {
    config()->set('laradocs.ui.last_updated_source', 'front_matter_or_mtime');
    $doc = makeDocument('intro', ['updated_at' => '2026-03-01']);

    expect(LastUpdatedConfig::resolve($doc))->toBe('1st March 2026');
});

it('falls back to mtime when front-matter is absent and source is front_matter_or_mtime', function () {
    config()->set('laradocs.ui.last_updated_source', 'front_matter_or_mtime');
    $doc = makeDocument('intro');

    $expected = CarbonImmutable::createFromTimestamp(1700000000)->format(
        config('laradocs.locale.date_format', 'jS F Y')
    );
    expect(LastUpdatedConfig::resolve($doc))->toBe($expected);
});

// ── custom closure ─────────────────────────────────────────────────────────────

it('uses a registered closure over any config source', function () {
    config()->set('laradocs.ui.last_updated_source', 'front_matter');

    LastUpdatedConfig::setResolver(fn (Document $doc) => 'custom-' . $doc->slug);

    $doc = makeDocument('intro', ['updated_at' => '2026-03-01']);

    expect(LastUpdatedConfig::resolve($doc))->toBe('custom-intro');
});

it('treats an empty string returned by the closure as null', function () {
    LastUpdatedConfig::setResolver(fn (Document $doc) => '');

    expect(LastUpdatedConfig::resolve(makeDocument('intro')))->toBeNull();
});

it('treats null returned by the closure as null', function () {
    LastUpdatedConfig::setResolver(fn (Document $doc) => null);

    expect(LastUpdatedConfig::resolve(makeDocument('intro')))->toBeNull();
});

it('reverts to config-driven resolution when closure is cleared', function () {
    config()->set('laradocs.ui.last_updated_source', 'front_matter');

    LastUpdatedConfig::setResolver(fn (Document $doc) => 'custom');
    LastUpdatedConfig::setResolver(null);

    $doc = makeDocument('intro', ['updated_at' => '2026-03-01']);

    expect(LastUpdatedConfig::resolve($doc))->toBe('1st March 2026');
});

// ── locale-aware formatting ────────────────────────────────────────────────────

it('translates the month name when a non-English docs locale is active', function () {
    app()->setLocale('de');

    $doc = makeDocument('intro', ['updated_at' => '2026-03-01']);

    // German month name — Carbon translates 'F' via translatedFormat().
    expect(LastUpdatedConfig::resolve($doc))->toContain('März');
})->after(fn () => app()->setLocale('en'));

it('formats mtime dates using the active locale', function () {
    app()->setLocale('de');

    config()->set('laradocs.ui.last_updated_source', 'mtime');
    $doc = makeDocument('intro'); // modifiedAt = 1700000000 → 15 Nov 2023

    expect(LastUpdatedConfig::resolve($doc))->toContain('November');
})->after(fn () => app()->setLocale('en'));

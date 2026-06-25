<?php

declare(strict_types=1);

use Laradocs\Metadata\Metadata;

it('applies defaults when keys are absent', function () {
    $meta = Metadata::fromArray([], ['order' => 5, 'hidden' => false]);

    expect($meta->order)->toBe(5)
        ->and($meta->hidden)->toBeFalse()
        ->and($meta->title)->toBeNull();
});

it('maps and coerces known fields', function () {
    $meta = Metadata::fromArray([
        'title' => 'Intro',
        'order' => '3',
        'hidden' => 1,
        'tags' => ['a', 'b'],
        'slug' => 'intro',
    ]);

    expect($meta->title)->toBe('Intro')
        ->and($meta->order)->toBe(3)
        ->and($meta->hidden)->toBeTrue()
        ->and($meta->tags)->toBe(['a', 'b'])
        ->and($meta->slug)->toBe('intro');
});

it('populates versionBanner from version_banner front-matter', function () {
    expect(Metadata::fromArray(['version_banner' => false])->versionBanner)->toBeFalse()
        ->and(Metadata::fromArray(['version_banner' => 'off'])->versionBanner)->toBeFalse()
        ->and(Metadata::fromArray(['version_banner' => true])->versionBanner)->toBeTrue();
});

it('leaves versionBanner null when unset and keeps it out of extra', function () {
    $meta = Metadata::fromArray(['version_banner' => false]);

    expect(Metadata::fromArray([])->versionBanner)->toBeNull()
        ->and($meta->extra)->toBe([]);
});

it('populates unchangedSince from unchanged_since front-matter', function () {
    $meta = Metadata::fromArray(['unchanged_since' => 'v1.0']);

    expect($meta->unchangedSince)->toBe('v1.0')
        ->and(Metadata::fromArray([])->unchangedSince)->toBeNull()
        ->and($meta->extra)->toBe([]);
});

it('normalises a scalar tag into a list', function () {
    expect(Metadata::fromArray(['tags' => 'solo'])->tags)->toBe(['solo']);
});

it('captures unknown keys as extra and exposes them via get()', function () {
    $meta = Metadata::fromArray(['title' => 'X', 'custom' => 'value']);

    expect($meta->extra)->toBe(['custom' => 'value'])
        ->and($meta->get('custom'))->toBe('value')
        ->and($meta->get('title'))->toBe('X')
        ->and($meta->get('nope', 'fallback'))->toBe('fallback');
});

it('round-trips through toArray()', function () {
    $array = Metadata::fromArray(['title' => 'T', 'order' => 2, 'extra_key' => 'e'])->toArray();

    expect($array['title'])->toBe('T')
        ->and($array['order'])->toBe(2)
        ->and($array['extra_key'])->toBe('e');
});

it('defaults order to PHP_INT_MAX when not set', function () {
    expect(Metadata::fromArray([])->order)->toBe(PHP_INT_MAX);
});

it('is searchable by default and honours search: false', function () {
    expect(Metadata::fromArray([])->searchable)->toBeTrue()
        ->and(Metadata::fromArray(['search' => false])->searchable)->toBeFalse()
        ->and(Metadata::fromArray(['search' => 'no'])->searchable)->toBeFalse()
        ->and(Metadata::fromArray(['search' => false])->toArray()['search'])->toBeFalse()
        ->and(Metadata::fromArray(['search' => false])->extra)->toBe([]);
});

it('updatedAtCarbon returns null when updated_at is absent', function () {
    expect(Metadata::fromArray([])->updatedAtCarbon())->toBeNull();
});

it('updatedAtCarbon parses a date string', function () {
    $carbon = Metadata::fromArray(['updated_at' => '2026-06-21'])->updatedAtCarbon();

    expect($carbon)->not->toBeNull()
        ->and($carbon->format('Y-m-d'))->toBe('2026-06-21');
});

it('updatedAtCarbon parses a unix timestamp string (YAML bare date conversion)', function () {
    // YAML 1.1 converts bare dates like 2026-06-21 to Unix timestamps.
    // nullableString() stores that integer as e.g. "1782000000".
    $timestamp = mktime(0, 0, 0, 6, 21, 2026);
    $carbon = Metadata::fromArray(['updated_at' => (string) $timestamp])->updatedAtCarbon();

    expect($carbon)->not->toBeNull()
        ->and($carbon->timestamp)->toBe($timestamp);
});

it('updatedAtCarbon returns null for unparseable values', function () {
    expect(Metadata::fromArray(['updated_at' => 'not-a-date'])->updatedAtCarbon())->toBeNull();
});

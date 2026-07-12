<?php

declare(strict_types=1);

use Laradocs\Documents\Document;
use Laradocs\Metadata\Metadata;
use Laradocs\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

// Pest 1 (the last PHP 7.3-compatible major) does not provide describe().
// Execute grouping callbacks eagerly while preserving their contained tests.
if (! function_exists('describe')) {
    function describe(string $description, Closure $tests): void
    {
        $tests();
    }
}

/**
 * Build a Document for tests without touching the filesystem.
 *
 * @param  array<string, mixed>  $meta
 */
function makeDocument(string $slug, array $meta = [], string $markdown = '', ?string $relativePath = null): Document
{
    return new Document('/virtual/' . $slug . '.md', $relativePath ?? ($slug === '' ? '_index.md' : $slug . '.md'), $slug, Metadata::fromArray($meta), $markdown, null, 1700000000);
}

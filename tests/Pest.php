<?php

declare(strict_types=1);

use Laradocs\Documents\Document;
use Laradocs\Metadata\Metadata;
use Laradocs\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * Build a Document for tests without touching the filesystem.
 *
 * @param  array<string, mixed>  $meta
 */
function makeDocument(string $slug, array $meta = [], string $markdown = '', ?string $relativePath = null): Document
{
    return new Document(
        path: '/virtual/' . $slug . '.md',
        relativePath: $relativePath ?? ($slug === '' ? '_index.md' : $slug . '.md'),
        slug: $slug,
        metadata: Metadata::fromArray($meta),
        markdown: $markdown,
        modifiedAt: 1700000000,
    );
}

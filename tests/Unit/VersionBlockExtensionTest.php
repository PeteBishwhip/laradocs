<?php

declare(strict_types=1);

use Laradocs\Extensions\VersionBlockExtension;

it('wraps a version-since block in a hidden div with its data attribute', function () {
    $extension = new VersionBlockExtension;

    $out = $extension->processMarkdown(":::version-since[2.0]\nNew in 2.0.\n:::");

    expect($out)->toContain('<div class="version-block" data-version-since="2.0" hidden>')
        ->and($out)->toContain('New in 2.0.')
        ->and($out)->toContain('</div>');
});

it('wraps version-until and version-only with correct data attributes', function () {
    $extension = new VersionBlockExtension;

    $until = $extension->processMarkdown(":::version-until[2.0]\nGone in 2.0.\n:::");
    $only = $extension->processMarkdown(":::version-only[1.0, 1.1]\nOnly here.\n:::");

    expect($until)->toContain('data-version-until="2.0" hidden')
        ->and($only)->toContain('data-version-only="1.0, 1.1" hidden');
});

it('surrounds inner markdown with blank lines so it is still parsed', function () {
    $extension = new VersionBlockExtension;

    $out = $extension->processMarkdown(":::version-since[2.0]\n## Heading\n\n- item\n:::");

    expect($out)->toContain(">\n\n## Heading")
        ->and($out)->toContain("- item\n\n</div>");
});

it('leaves unrelated markdown untouched', function () {
    $extension = new VersionBlockExtension;

    expect($extension->processMarkdown("# Title\n\nBody text."))
        ->toBe("# Title\n\nBody text.");
});

it('emits a matching block without hidden in server mode', function () {
    config()->set('laradocs._current_version', 'v2.0');
    $extension = new VersionBlockExtension(true);

    $out = $extension->processMarkdown(":::version-since[2.0]\nNew in 2.0.\n:::");

    expect($out)->toContain('<div class="version-block" data-version-since="2.0">')
        ->and($out)->not->toContain('hidden')
        ->and($out)->toContain('New in 2.0.');
});

it('strips a non-matching version-until block in server mode', function () {
    config()->set('laradocs._current_version', 'v2.0');
    $extension = new VersionBlockExtension(true);

    $out = $extension->processMarkdown("Before\n\n:::version-until[2.0]\nGone.\n:::\n\nAfter");

    expect($out)->not->toContain('version-block')
        ->and($out)->not->toContain('Gone.')
        ->and($out)->toContain('Before')
        ->and($out)->toContain('After');
});

it('strips a non-matching version-only block in server mode', function () {
    config()->set('laradocs._current_version', 'v2.0');
    $extension = new VersionBlockExtension(true);

    $out = $extension->processMarkdown(":::version-only[1.0, 1.1]\nOld notes.\n:::");

    expect($out)->toBe('');
});

it('keeps a version-only block when the current version is listed', function () {
    config()->set('laradocs._current_version', 'v1.1');
    $extension = new VersionBlockExtension(true);

    $out = $extension->processMarkdown(":::version-only[1.0, 1.1]\nListed.\n:::");

    expect($out)->toContain('data-version-only="1.0, 1.1"')
        ->and($out)->not->toContain('hidden')
        ->and($out)->toContain('Listed.');
});

it('strips server-mode blocks when no current version is set', function () {
    config()->set('laradocs._current_version', null);
    $extension = new VersionBlockExtension(true);

    expect($extension->processMarkdown(":::version-since[2.0]\nNew.\n:::"))->toBe('');
});

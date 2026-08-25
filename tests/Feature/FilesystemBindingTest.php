<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Laradocs\Contracts\DocumentLoader;
use Laradocs\Laradocs;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * A filesystem that walks into symlinked directories, which Symfony Finder
 * skips unless asked. Stands in here for any application-specific filesystem.
 */
function symlinkAwareFilesystem(): void
{
    app()->bind(Filesystem::class, fn (): Filesystem => new class extends Filesystem
    {
        /**
         * @return array<int, SplFileInfo>
         */
        public function allFiles($directory, $hidden = false): array
        {
            return iterator_to_array(
                Finder::create()->files()->followLinks()->ignoreDotFiles(! $hidden)->in($directory)->sortByName(),
                false,
            );
        }
    });
}

it('reads documents with the shared filesystem by default', function () {
    $this->makeDocs(['guide.md' => "---\ntitle: Guide\n---\n\nBody.\n"]);

    expect(app(Laradocs::class)->all())->toHaveCount(1);
});

it('uses a filesystem the application has bound', function () {
    $root = $this->makeDocs(['guide.md' => "---\ntitle: Guide\n---\n\nBody.\n"]);

    $elsewhere = sys_get_temp_dir() . '/laradocs-linked-' . bin2hex(random_bytes(4));
    (new Filesystem)->ensureDirectoryExists($elsewhere);
    (new Filesystem)->put($elsewhere . '/linked.md', "---\ntitle: Linked\n---\n\nLinked body.\n");
    symlink($elsewhere, $root . '/linked');

    try {
        // The default filesystem does not descend into the symlink.
        expect(app(Laradocs::class)->all())->toHaveCount(1);

        symlinkAwareFilesystem();
        app()->forgetInstance(DocumentLoader::class);
        app()->forgetInstance(Laradocs::class);

        expect(app(Laradocs::class)->all()->pluck('slug')->all())
            ->toContain('linked/linked');
    } finally {
        unlink($root . '/linked');
        (new Filesystem)->deleteDirectory($elsewhere);
    }
});

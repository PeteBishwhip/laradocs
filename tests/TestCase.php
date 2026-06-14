<?php

declare(strict_types=1);

namespace Laradocs\Tests;

use Illuminate\Filesystem\Filesystem;
use Laradocs\LaradocsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RalphJSmit\Laravel\SEO\LaravelSEOServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * Temporary docs directories created during a test, cleaned up after.
     *
     * @var array<int, string>
     */
    protected array $tempDocs = [];

    protected function tearDown(): void
    {
        $filesystem = new Filesystem;

        foreach ($this->tempDocs as $path) {
            $filesystem->deleteDirectory($path);
        }

        parent::tearDown();
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LaravelSEOServiceProvider::class,
            LaradocsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('cache.default', 'array');
        $app['config']->set('laradocs.cache.enabled', false);
        // Default to the dependency-free engine so tests never reach out to a
        // real Scout backend; Scout-specific tests opt in explicitly.
        $app['config']->set('laradocs.search.driver', 'json');
    }

    /**
     * Build a throwaway docs directory from a path => contents map and point
     * the package's configured docs path at it.
     *
     * @param  array<string, string>  $files
     */
    protected function makeDocs(array $files): string
    {
        $filesystem = new Filesystem;
        $root = sys_get_temp_dir() . '/laradocs-' . bin2hex(random_bytes(6));

        foreach ($files as $relative => $contents) {
            $full = $root . '/' . ltrim($relative, '/');
            $filesystem->ensureDirectoryExists(dirname($full));
            $filesystem->put($full, $contents);
        }

        $this->tempDocs[] = $root;

        config()->set('laradocs.docs.path', $root);

        return $root;
    }
}

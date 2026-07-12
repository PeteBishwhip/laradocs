<?php

declare(strict_types=1);

namespace Laradocs\Tests;

use Dedoc\Scramble\ScrambleServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Contracts\Console\Kernel;
use Laradocs\LaradocsServiceProvider;
use Laravel\Mcp\Server\McpServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Laradocs\Tests\Support\PendingCommand;

abstract class TestCase extends Orchestra
{
    /**
     * Temporary docs directories created during a test, cleaned up after.
     *
     * @var array<int, string>
     */
    protected $tempDocs = [];

    public function artisan($command, $parameters = [])
    {
        if (! $this->mockConsoleOutput) {
            return $this->app[Kernel::class]->call($command, $parameters);
        }

        return new PendingCommand($this, $this->app, $command, $parameters);
    }

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
        $providers = [LaradocsServiceProvider::class];

        // laravel/mcp is an optional dependency. When it is installed, register
        // its service provider so the MCP request/argument container bindings
        // are wired up for the MCP integration tests; auto-discovery handles
        // this in a real application.
        if (class_exists(McpServiceProvider::class)) {
            $providers[] = McpServiceProvider::class;
        }

        // dedoc/scramble is likewise optional. Its provider must be registered
        // for the Scramble generator driver to resolve (it binds PhpParser\Parser
        // and Scramble's own config); auto-discovery handles this in a real
        // application.
        if (class_exists(ScrambleServiceProvider::class)) {
            $providers[] = ScrambleServiceProvider::class;
        }

        return $providers;
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('cache.default', 'array');
        $app['config']->set('laradocs.cache.enabled', false);
        // Default to the dependency-free engine so tests never reach out to a
        // real Scout backend; Scout-specific tests opt in explicitly.
        $app['config']->set('laradocs.search.driver', 'json');
        // CI installs Scramble in every job, so the default `auto` OpenAPI
        // driver would silently switch every generator test to Scramble. Pin
        // the built-in generator; Scramble-specific tests opt in explicitly
        // with --driver=scramble.
        $app['config']->set('laradocs.openapi.generator.driver', 'native');
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

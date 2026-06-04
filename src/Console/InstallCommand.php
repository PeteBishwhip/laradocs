<?php

declare(strict_types=1);

namespace Laradocs\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Blade;
use Laradocs\Support\Config;

final class InstallCommand extends Command
{
    protected $signature = 'laradocs:install {--force : Overwrite existing files}';

    protected $description = 'Publish the Laradocs config and scaffold a starter docs folder';

    public function handle(Filesystem $files): int
    {
        $this->call('vendor:publish', [
            '--tag' => 'laradocs-config',
            '--force' => (bool) $this->option('force'),
        ]);

        $path = Config::string('laradocs.docs.path');
        $files->ensureDirectoryExists($path);

        $index = $path . '/index.md';

        if (! $files->exists($index) || $this->option('force')) {
            $files->put($index, $this->stub());
            $this->components->info('Created starter document at ' . $index);
        } else {
            $this->components->warn('A document already exists at ' . $index);
        }

        $this->components->info('Laradocs installed. Visit /' . Config::string('laradocs.route.prefix') . ' to view your docs.');
        $this->components->info('Customise the page templates with: php artisan vendor:publish --tag=laradocs-stubs');

        return self::SUCCESS;
    }

    private function stub(): string
    {
        $published = base_path('stubs/laradocs/welcome.blade.php');
        $package = __DIR__ . '/../../stubs/welcome.blade.php';

        $path = is_file($published) ? $published : $package;

        return Blade::render((string) file_get_contents($path), [], deleteCachedView: true);
    }
}

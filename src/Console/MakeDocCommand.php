<?php

declare(strict_types=1);

namespace Laradocs\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Laradocs\Support\Config;

final class MakeDocCommand extends Command
{
    protected $signature = 'make:doc {name : The doc path, e.g. guide/getting-started}
        {--title= : The document title}
        {--group= : The sidebar group}
        {--order= : The sort order}
        {--force : Overwrite if the file exists}';

    protected $description = 'Scaffold a new markdown documentation page with front-matter';

    public function handle(Filesystem $files): int
    {
        $name = trim((string) $this->argument('name'), '/');
        $relative = Str::endsWith($name, ['.md', '.markdown']) ? $name : $name . '.md';

        $path = rtrim(Config::string('laradocs.docs.path'), '/') . '/' . $relative;

        if ($files->exists($path) && ! $this->option('force')) {
            $this->error('Document already exists: ' . $path);

            return self::FAILURE;
        }

        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, $this->stub($name));

        $this->info('Created ' . $path);

        return self::SUCCESS;
    }

    private function stub(string $name): string
    {
        $titleOption = $this->option('title');
        $title = is_string($titleOption) && $titleOption !== ''
            ? $titleOption
            : (string) Str::of(basename($name))->replace(['-', '_'], ' ')->title();

        $groupOption = $this->option('group');
        $group = is_string($groupOption) && $groupOption !== '' ? $groupOption : null;

        $orderOption = $this->option('order');
        $order = is_numeric($orderOption) ? (int) $orderOption : null;

        $published = base_path('stubs/laradocs/page.blade.php');
        $package = __DIR__ . '/../../stubs/page.blade.php';

        $path = is_file($published) ? $published : $package;

        return view()->file($path, [
            'title' => $title,
            'group' => $group,
            'order' => $order,
            'name' => $name,
        ])->render();
    }
}

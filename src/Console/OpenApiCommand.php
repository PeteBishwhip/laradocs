<?php

declare(strict_types=1);

namespace Laradocs\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Router;
use Laradocs\OpenApi\Generator\RequestInspector;
use Laradocs\OpenApi\Generator\ResponseInspector;
use Laradocs\OpenApi\Generator\RouteCollector;
use Laradocs\OpenApi\Generator\SpecGenerator;
use Laradocs\Support\Config;
use Symfony\Component\Yaml\Yaml;

/**
 * Discovers the application's API routes and writes a scaffold OpenAPI spec by
 * reflecting FormRequests and JsonResources for input/output schemas.
 *
 * This is the Pillar B counterpart to the read-side OpenAPI rendering: rather
 * than rendering a hand-written spec, it bootstraps one from the route surface
 * so a host app can start from something close instead of a blank file.
 */
final class OpenApiCommand extends Command
{
    protected $signature = 'laradocs:openapi
        {--output= : Path to write the generated spec to (overrides config)}
        {--prefix= : Only include routes under this URI prefix}
        {--middleware= : Only include routes carrying this middleware}
        {--force : Overwrite the output file if it already exists}';

    protected $description = 'Generate an OpenAPI spec from your API routes, FormRequests and Resources';

    public function handle(Router $router, Filesystem $files): int
    {
        if (! class_exists(Yaml::class)) {
            $this->components->error('symfony/yaml is required to generate an OpenAPI spec.');

            return self::FAILURE;
        }

        $prefix = $this->resolve('prefix', 'laradocs.openapi.generator.prefix', 'api');
        $middleware = $this->resolve('middleware', 'laradocs.openapi.generator.middleware', 'api');

        $generator = new SpecGenerator(
            routes: new RouteCollector($router, $prefix, $middleware),
            requests: new RequestInspector,
            responses: new ResponseInspector,
            title: Config::string('laradocs.openapi.generator.title', Config::string('laradocs.openapi.title', 'API')),
            version: Config::string('laradocs.openapi.generator.version', '1.0.0'),
            serverUrl: $this->serverUrl(),
        );

        $spec = $generator->generate();

        /** @var array<string, mixed> $paths */
        $paths = $spec['paths'] ?? [];

        if ($paths === []) {
            $this->components->warn('No API routes matched the configured prefix/middleware filters.');
        }

        $output = $this->outputPath();

        if ($files->exists($output) && ! $this->option('force')) {
            $this->components->error('Spec already exists (use --force to overwrite): ' . $output);

            return self::FAILURE;
        }

        $files->ensureDirectoryExists(dirname($output));
        $files->put($output, Yaml::dump($spec, 8, 2, Yaml::DUMP_OBJECT_AS_MAP));

        $this->components->info(sprintf('Wrote %d path(s) to %s', count($paths), $output));

        return self::SUCCESS;
    }

    /**
     * Resolve a string filter from the command option, falling back to config
     * then the default. An empty option string disables the filter (null).
     */
    private function resolve(string $option, string $configKey, string $default): ?string
    {
        $value = $this->option($option);

        if (is_string($value)) {
            return $value === '' ? null : $value;
        }

        $configured = Config::nullableString($configKey);

        return $configured ?? $default;
    }

    private function serverUrl(): ?string
    {
        $configured = Config::nullableString('laradocs.openapi.generator.server_url');

        if ($configured !== null && $configured !== '') {
            return $configured;
        }

        return Config::nullableString('app.url');
    }

    private function outputPath(): string
    {
        $option = $this->option('output');

        $path = is_string($option) && $option !== ''
            ? $option
            : Config::string('laradocs.openapi.generator.output', 'docs/api/openapi.yaml');

        return $this->isAbsolute($path) ? $path : base_path($path);
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || (bool) preg_match('/^[A-Za-z]:[\\\\\/]/', $path);
    }
}

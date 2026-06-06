<?php

declare(strict_types=1);

namespace Laradocs;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\Registrar;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laradocs\Cache\DocumentCache;
use Laradocs\Console\CacheCommand;
use Laradocs\Console\ClearCommand;
use Laradocs\Console\IndexCommand;
use Laradocs\Console\InstallCommand;
use Laradocs\Console\MakeDocCommand;
use Laradocs\Contracts\DocumentLoader;
use Laradocs\Contracts\DocumentParser;
use Laradocs\Contracts\HtmlExtension;
use Laradocs\Contracts\MarkdownExtension;
use Laradocs\Contracts\MetadataResolver;
use Laradocs\Extensions\CalloutExtension;
use Laradocs\Extensions\CodeBlockExtension;
use Laradocs\Extensions\HeadingAnchorExtension;
use Laradocs\Extensions\ImageExtension;
use Laradocs\Extensions\MacroExtension;
use Laradocs\Extensions\VariableExtension;
use Laradocs\Extensions\VideoExtension;
use Laradocs\Loaders\FilesystemLoader;
use Laradocs\Macros\MacroRegistry;
use Laradocs\Metadata\FrontMatterMetadataResolver;
use Laradocs\Parsers\MarkdownParser;
use Laradocs\Routing\DocumentRouter;
use Laradocs\Routing\SlugResolver;
use Laradocs\Search\Contracts\SearchEngine;
use Laradocs\Search\JsonSearchEngine;
use Laradocs\Search\ScoutSearchEngine;
use Laradocs\Search\SearchManager;
use Laradocs\Seo\SeoFactory;
use Laradocs\Support\Config;
use Laradocs\Support\RateLimiterConfig;
use Laradocs\Variables\VariableRegistry;
use Laravel\Scout\EngineManager;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

final class LaradocsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laradocs.php', 'laradocs');

        $this->registerRegistries();
        $this->registerPipeline();
        $this->registerCore();
        $this->registerSearch();
        $this->registerRateLimiting();
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'laradocs');
        $this->registerRoutes();
        $this->bootRateLimiting();
        $this->registerDefaultMacros();

        if ($this->app->runningInConsole()) {
            $this->registerPublishing();
            $this->registerCommands();
            $this->registerAbout();
        }
    }

    private function registerRegistries(): void
    {
        $this->app->singleton(VariableRegistry::class, function (): VariableRegistry {
            /** @var array<string, mixed> $values */
            $values = Config::array('laradocs.variables');

            return new VariableRegistry($values);
        });

        $this->app->singleton(MacroRegistry::class, function (): MacroRegistry {
            /** @var array<string, \Closure|string> $macros */
            $macros = Config::array('laradocs.macros');

            return new MacroRegistry($macros);
        });

        $this->app->singleton(SlugResolver::class, fn (): SlugResolver => new SlugResolver(
            Config::string('laradocs.routing.strategy', 'both'),
            Config::string('laradocs.docs.index', '_index'),
        ));

        $this->app->singleton(MetadataResolver::class, FrontMatterMetadataResolver::class);
    }

    private function registerPipeline(): void
    {
        $this->app->singleton(DocumentParser::class, function (Application $app): MarkdownParser {
            return new MarkdownParser(
                $this->buildConverter($app),
                $this->markdownExtensions($app),
                $this->htmlExtensions($app),
            );
        });

        $this->app->bind(DocumentLoader::class, function (Application $app): FilesystemLoader {
            /** @var array<int, string> $extensions */
            $extensions = Config::array('laradocs.docs.extensions', ['md']);
            /** @var array<int, string> $ignored */
            $ignored = Config::array('laradocs.docs.ignored_patterns');
            /** @var array<string, mixed> $defaults */
            $defaults = Config::array('laradocs.metadata.default');

            return new FilesystemLoader(
                new Filesystem,
                $app->make(MetadataResolver::class),
                $app->make(SlugResolver::class),
                Config::string('laradocs.docs.path'),
                $extensions,
                $ignored,
                $defaults,
            );
        });

        $this->app->bind(DocumentCache::class, function (Application $app): DocumentCache {
            /** @var CacheFactory $factory */
            $factory = $app->make(CacheFactory::class);

            return new DocumentCache(
                $factory->store(Config::nullableString('laradocs.cache.store')),
                Config::bool('laradocs.cache.enabled', true),
                Config::nullableInt('laradocs.cache.ttl'),
                Config::string('laradocs.cache.key_prefix', 'laradocs'),
            );
        });
    }

    private function registerCore(): void
    {
        $this->app->singleton(SeoFactory::class);

        $this->app->bind(Laradocs::class, function (Application $app): Laradocs {
            /** @var array<int, string> $searchExclude */
            $searchExclude = Config::array('laradocs.search.exclude');
            /** @var array<int, string> $searchInclude */
            $searchInclude = Config::array('laradocs.search.include');
            /** @var array<string, float> $searchRank */
            $searchRank = Config::array('laradocs.search.rank');

            return new Laradocs(
                $app->make(DocumentLoader::class),
                $app->make(DocumentParser::class),
                $app->make(DocumentCache::class),
                $app->make(VariableRegistry::class),
                $app->make(MacroRegistry::class),
                $app->make(RateLimiterConfig::class),
                Config::string('laradocs.docs.index', '_index'),
                Config::int('laradocs.search.max_chars', 10000),
                $searchExclude,
                $searchInclude,
                $searchRank,
            );
        });
    }

    private function registerRateLimiting(): void
    {
        $this->app->singleton(RateLimiterConfig::class);
    }

    private function bootRateLimiting(): void
    {
        RateLimiter::for('laradocs-api', function (Request $request): Limit {
            $resolver = $this->app->make(RateLimiterConfig::class)->get();

            if ($resolver instanceof \Closure) {
                /** @var Limit $limit */
                $limit = $resolver($request);

                return $limit;
            }

            $perMinute = is_int($resolver) ? $resolver : Config::int('laradocs.api.rate_limit', 60);

            return Limit::perMinute($perMinute)->by($request->ip());
        });
    }

    private function registerSearch(): void
    {
        $this->app->singleton(SearchManager::class, function (Application $app): SearchManager {
            $index = Config::string('laradocs.search.index', 'laradocs');

            return new SearchManager(
                Config::string('laradocs.search.driver', 'auto'),
                class_exists(EngineManager::class),
                self::scoutIsConfigured(),
                fn (): SearchEngine => new ScoutSearchEngine($app->make(EngineManager::class), $index),
                new JsonSearchEngine,
            );
        });

        $this->app->bind(
            SearchEngine::class,
            fn (Application $app): SearchEngine => $app->make(SearchManager::class)->engine(),
        );
    }

    /**
     * Treat Scout as "configured" only when there's a real intent signal.
     *
     * Scout's package config merges a default of `'algolia'` whenever it's
     * installed, so `config('scout.driver')` alone isn't reliable — auto-
     * mode would pick Scout on hosts that never wired it up, then fail at
     * query time when Algolia has no credentials. Instead we treat any
     * driver other than the bare default as intent (the user picked it),
     * and for the `'algolia'` default we also require an Algolia App ID.
     * This works in both cached-config and live-env setups.
     */
    public static function scoutIsConfigured(): bool
    {
        $driver = Config::nullableString('scout.driver');

        if ($driver === null || $driver === '') {
            return false;
        }

        if ($driver === 'algolia') {
            return Config::nullableString('scout.algolia.id') !== null
                && Config::nullableString('scout.algolia.id') !== '';
        }

        return true;
    }

    private function buildConverter(Application $app): MarkdownConverter
    {
        $extensions = Config::array('laradocs.parser.extensions');

        $environment = new Environment([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);

        if ($extensions['gfm'] ?? true) {
            $environment->addExtension(new GithubFlavoredMarkdownExtension);
        }

        if ($extensions['attributes'] ?? true) {
            $environment->addExtension(new AttributesExtension);
        }

        if ($extensions['footnotes'] ?? true) {
            $environment->addExtension(new FootnoteExtension);
        }

        return new MarkdownConverter($environment);
    }

    /**
     * @return array<int, MarkdownExtension>
     */
    private function markdownExtensions(Application $app): array
    {
        $config = Config::array('laradocs.parser.extensions');
        $extensions = [];

        if ($config['variables'] ?? true) {
            $extensions[] = new VariableExtension(
                $app->make(VariableRegistry::class),
                Config::string('laradocs.parser.unknown_variable', 'blank'),
            );
        }

        if ($config['macros'] ?? true) {
            $extensions[] = new MacroExtension($app->make(MacroRegistry::class));
        }

        return $extensions;
    }

    /**
     * @return array<int, HtmlExtension>
     */
    private function htmlExtensions(Application $app): array
    {
        $config = Config::array('laradocs.parser.extensions');
        $extensions = [];

        if ($config['heading_anchors'] ?? true) {
            $extensions[] = new HeadingAnchorExtension;
        }

        if ($config['callouts'] ?? true) {
            $extensions[] = new CalloutExtension;
        }

        if ($config['video'] ?? true) {
            $extensions[] = new VideoExtension;
        }

        if ($config['images'] ?? true) {
            $extensions[] = new ImageExtension;
        }

        $extensions[] = new CodeBlockExtension;

        return $extensions;
    }

    private function registerRoutes(): void
    {
        // Routes are always registered so that route:cache captures them
        // regardless of the current `enabled` flag. The EnsureDocsEnabled
        // middleware enforces the toggle at request time instead.
        /** @var Registrar $router */
        $router = $this->app->make(Registrar::class);

        (new DocumentRouter)->register($router, Config::array('laradocs.route'));
    }

    private function registerDefaultMacros(): void
    {
        $macros = $this->app->make(MacroRegistry::class);

        foreach (['alert', 'badge', 'button', 'embed'] as $name) {
            if (! $macros->has($name)) {
                $macros->register($name, "laradocs::macros.{$name}");
            }
        }
    }

    private function registerPublishing(): void
    {
        $this->publishes([
            __DIR__ . '/../config/laradocs.php' => $this->app->configPath('laradocs.php'),
        ], 'laradocs-config');

        $this->publishes([
            __DIR__ . '/../resources/views' => $this->app->resourcePath('views/vendor/laradocs'),
        ], 'laradocs-views');

        $this->publishes([
            __DIR__ . '/../resources/dist' => $this->app->publicPath('vendor/laradocs'),
        ], 'laradocs-assets');

        $this->publishes([
            __DIR__ . '/../stubs' => $this->app->basePath('stubs/laradocs'),
        ], 'laradocs-stubs');
    }

    private function registerCommands(): void
    {
        $this->commands([
            InstallCommand::class,
            MakeDocCommand::class,
            CacheCommand::class,
            ClearCommand::class,
            IndexCommand::class,
        ]);

        $this->optimizes('laradocs:cache', 'laradocs:clear');
    }

    private function registerAbout(): void
    {
        AboutCommand::add('Laradocs', fn (): array => [
            'Route Prefix' => '/' . Config::string('laradocs.route.prefix'),
            'Docs Path' => Config::string('laradocs.docs.path'),
            'Caching' => Config::bool('laradocs.cache.enabled') ? 'enabled' : 'disabled',
            'Search Driver' => Config::string('laradocs.search.driver'),
            'Theme' => Config::string('laradocs.ui.theme'),
            'Banner' => Config::bool('laradocs.ui.banner.enabled') ? Config::string('laradocs.ui.banner.type', 'info') : 'disabled',
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Laradocs;

use cebe\openapi\Reader;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\Registrar;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laradocs\Cache\DocumentCache;
use Laradocs\Console\CacheCommand;
use Laradocs\Console\CheckCommand;
use Laradocs\Console\ClearCommand;
use Laradocs\Console\CloneProjectCommand;
use Laradocs\Console\ConfigCommand;
use Laradocs\Console\DeployCommand;
use Laradocs\Console\IndexCommand;
use Laradocs\Console\InstallCommand;
use Laradocs\Console\LangCommand;
use Laradocs\Console\LintCommand;
use Laradocs\Console\LoginCommand;
use Laradocs\Console\MakeDocCommand;
use Laradocs\Console\OpenApiCommand;
use Laradocs\Console\VersionsCommand;
use Laradocs\Contracts\DocumentContentRenderer;
use Laradocs\Contracts\DocumentLoader;
use Laradocs\Contracts\DocumentParser;
use Laradocs\Contracts\MetadataResolver;
use Laradocs\Contracts\OgImageGenerator;
use Laradocs\Icons\HeroiconProvider;
use Laradocs\Icons\IconRegistry;
use Laradocs\Loaders\CompositeDocumentLoader;
use Laradocs\Loaders\FilesystemLoader;
use Laradocs\Loaders\OpenApiLoader;
use Laradocs\Macros\MacroRegistry;
use Laradocs\Metadata\FrontMatterMetadataResolver;
use Laradocs\OpenApi\OpenApiContentRenderer;
use Laradocs\OpenApi\OpenApiParser;
use Laradocs\Parsers\MarkdownParser;
use Laradocs\Parsers\MarkdownPipelineFactory;
use Laradocs\Routing\DocumentRouter;
use Laradocs\Routing\SlugResolver;
use Laradocs\Search\Contracts\SearchEngine;
use Laradocs\Search\JsonSearchEngine;
use Laradocs\Search\ScoutSearchEngine;
use Laradocs\Search\SearchManager;
use Laradocs\Seo\SeoFactory;
use Laradocs\Seo\TheOgImageGenerator;
use Laradocs\Support\Config;
use Laradocs\Support\Locale;
use Laradocs\Support\RateLimiterConfig;
use Laradocs\Support\Version;
use Laradocs\Support\VersionRegistry;
use Laradocs\Variables\VariableRegistry;
use Laravel\Scout\EngineManager;
use SimonHamp\TheOg\Image;

final class LaradocsServiceProvider extends ServiceProvider
{
    private const CONFIG = __DIR__ . '/../config/laradocs.php';

    private const VIEWS = __DIR__ . '/../resources/views';

    private const LANG = __DIR__ . '/../resources/lang';

    private const DIST = __DIR__ . '/../resources/dist';

    private const STUBS = __DIR__ . '/../stubs';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG, 'laradocs');

        $this->registerRegistries();
        $this->registerPipeline();
        $this->registerCore();
        $this->registerSearch();
        $this->registerRateLimiting();
    }

    public function boot(): void
    {
        $this->loadViewsFrom(self::VIEWS, 'laradocs');
        $this->loadTranslationsFrom(self::LANG, 'laradocs');
        $this->registerRoutes();
        $this->bootRateLimiting();
        $this->bootOctaneSafety();
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

        $this->app->singleton(IconRegistry::class, function (Application $app): IconRegistry {
            $default = Config::string('laradocs.icons.driver', 'heroicons');
            $registry = new IconRegistry($default);

            $heroiconsPath = Config::nullableString('laradocs.icons.heroicons.path')
                ?? HeroiconProvider::detect();

            if ($heroiconsPath !== null) {
                $provider = new HeroiconProvider($heroiconsPath, $app->make(Filesystem::class));
                $registry->register('heroicons', fn (string $name, string $variant): string => $provider($name, $variant));
            }

            return $registry;
        });

        $this->app->singleton(SlugResolver::class, fn (): SlugResolver => new SlugResolver(
            Config::string('laradocs.routing.strategy', 'both'),
            Config::string('laradocs.docs.index', '_index'),
        ));

        $this->app->singleton(MetadataResolver::class, FrontMatterMetadataResolver::class);

        // The authoritative version list: discovers, sorts and resolves
        // documentation versions once per request for every consumer.
        $this->app->singleton(VersionRegistry::class);
    }

    private function registerPipeline(): void
    {
        $this->app->singleton(DocumentParser::class, function (Application $app): MarkdownParser {
            return new MarkdownParser(
                MarkdownPipelineFactory::buildConverter(),
                MarkdownPipelineFactory::markdownExtensions($app),
                MarkdownPipelineFactory::htmlExtensions(),
            );
        });

        $this->app->bind(DocumentLoader::class, function (Application $app): DocumentLoader {
            $filesystem = $this->makeFilesystemLoader($app);

            // The OpenAPI integration only participates when it is switched on
            // *and* the optional cebe library is installed; otherwise the
            // behaviour is byte-for-byte the original filesystem loader.
            if (Config::bool('laradocs.openapi.enabled', false) && class_exists(Reader::class)) {
                return new CompositeDocumentLoader([
                    $filesystem,
                    $this->makeOpenApiLoader($app),
                ]);
            }

            return $filesystem;
        });

        $this->app->bind(DocumentCache::class, function (Application $app): DocumentCache {
            /** @var CacheFactory $factory */
            $factory = $app->make(CacheFactory::class);

            return new DocumentCache(
                $factory->store(Config::nullableString('laradocs.cache.store')),
                Config::bool('laradocs.cache.enabled', true),
                Config::nullableInt('laradocs.cache.ttl'),
            );
        });
    }

    /**
     * The filesystem-backed document loader: the package's original source of
     * documents, reading markdown files from the active docs path.
     */
    private function makeFilesystemLoader(Application $app): FilesystemLoader
    {
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
            fn (): string => Version::docsPath(),
            $extensions,
            $ignored,
            $defaults,
            // Content localisation recognises the same locales offered in
            // the in-page switcher. Per-language pages (page.fr.md or
            // fr/page.md) are served for the request's locale, falling back
            // to the default-locale file when a translation is missing.
            fn (): array => array_keys(Locale::available()),
            fn (): string => (string) $app->getLocale(),
            fn (): string => Locale::fallback(),
        );
    }

    /**
     * The OpenAPI loader, surfacing each spec operation as a synthetic document.
     * Shares the document cache store/ttl so parsed specs cache alongside
     * everything else.
     */
    private function makeOpenApiLoader(Application $app): OpenApiLoader
    {
        /** @var array<int, string> $files */
        $files = Config::array('laradocs.openapi.files', ['openapi.yaml', 'openapi.yml', 'openapi.json']);

        return new OpenApiLoader(
            $this->makeOpenApiParser($app),
            fn (): string => Version::docsPath(),
            $files,
            Config::string('laradocs.openapi.base_slug', 'api'),
            Config::nullableString('laradocs.openapi.title'),
            Config::nullableString('laradocs.openapi.group'),
            Config::int('laradocs.openapi.order'),
            fn (): string => (string) $app->getLocale(),
        );
    }

    /**
     * Build an OpenAPI parser sharing the document cache store/ttl so parsed
     * specs cache alongside everything else. Used by both the loader and the
     * content renderer.
     */
    private function makeOpenApiParser(Application $app): OpenApiParser
    {
        /** @var CacheFactory $factory */
        $factory = $app->make(CacheFactory::class);

        return new OpenApiParser(
            $factory->store(Config::nullableString('laradocs.cache.store')),
            Config::bool('laradocs.cache.enabled', true),
            Config::nullableInt('laradocs.cache.ttl'),
        );
    }

    private function registerCore(): void
    {
        $this->app->singleton(SeoFactory::class);

        // Default social-card generator. Bound only when simonhamp/the-og is
        // installed so the package stays dependency-light; consumers can bind
        // their own OgImageGenerator (in any provider) to override it, with or
        // without the-og present. OgImage::enabled() gates the SEO/route layer
        // on this binding existing, so an unbound contract never 500s a card.
        if (class_exists(Image::class)) {
            $this->app->bindIf(OgImageGenerator::class, TheOgImageGenerator::class);
        }

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
                $this->makeContentRenderers($app),
            );
        });
    }

    /**
     * The native document content renderers consulted at the HTML choke-point
     * (US-002). The OpenAPI renderer only participates when the integration is
     * switched on *and* the optional cebe library is installed — mirroring the
     * loader binding — so markdown rendering is otherwise untouched.
     *
     * @return array<int, DocumentContentRenderer>
     */
    private function makeContentRenderers(Application $app): array
    {
        $renderers = [];

        if (Config::bool('laradocs.openapi.enabled', false) && class_exists(Reader::class)) {
            $renderers[] = new OpenApiContentRenderer(
                $this->makeOpenApiParser($app),
                $app->make(DocumentParser::class),
                Config::bool('laradocs.openapi.render_markdown_descriptions', true),
            );
        }

        return $renderers;
    }

    private function registerRateLimiting(): void
    {
        $this->app->singleton(RateLimiterConfig::class);
    }

    /**
     * On long-lived workers (Octane / RoadRunner) singletons survive across
     * requests. SeoFactory carries $lastXCard as per-request scratch state, so
     * we drop its resolved singleton at the start of every new request: the
     * next resolve rebuilds it fresh, with no value carried over from the
     * previous render. Forgetting the instance also covers any future
     * per-request state on the factory without a hand-maintained reset method.
     *
     * The listener is keyed by Octane's event class name as a string, so it
     * registers cleanly whether or not Octane is installed: without Octane the
     * event is never dispatched and the listener simply stays inert.
     */
    private function bootOctaneSafety(): void
    {
        /** @var Dispatcher $events */
        $events = $this->app->make('events');

        $events->listen(
            'Laravel\Octane\Events\RequestReceived',
            function (): void {
                $this->app->forgetInstance(SeoFactory::class);
            },
        );
    }

    private function bootRateLimiting(): void
    {
        RateLimiter::for('laradocs-api', function (Request $request): Limit {
            $resolver = $this->app->make(RateLimiterConfig::class)->get();

            if ($resolver === false) {
                return Limit::none();
            }

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

    private function registerRoutes(): void
    {
        // Routes are always registered so that route:cache captures them
        // regardless of the current `enabled` flag. The EnsureDocsEnabled
        // middleware enforces the toggle at request time instead.
        //
        // Consumer apps that want to own the docs URL (e.g. for tenant
        // routing) can set `laradocs.route.register => false` and wire the
        // render action into their own route instead.
        if (! Config::bool('laradocs.route.register', true)) {
            return;
        }

        /** @var Registrar $router */
        $router = $this->app->make(Registrar::class);

        (new DocumentRouter)->register($router, Config::array('laradocs.route'));
    }

    private function registerDefaultMacros(): void
    {
        $macros = $this->app->make(MacroRegistry::class);

        foreach (['alert', 'badge', 'button', 'callout', 'embed'] as $name) {
            if (! $macros->has($name)) {
                $macros->register($name, "laradocs::macros.{$name}");
            }
        }

        $icons = $this->app->make(IconRegistry::class);
        $defaultVariant = Config::string('laradocs.icons.heroicons.variant', 'outline');

        if (! $macros->has('icon')) {
            $macros->register('icon', function (array $arguments) use ($icons, $defaultVariant): string {
                [$name, $variant, $set] = self::parseIconArguments($arguments, $defaultVariant);

                return $icons->render($name, $variant, $set);
            });
        }

        if (! $macros->has('icon:heroicons')) {
            $macros->register('icon:heroicons', function (array $arguments) use ($icons, $defaultVariant): string {
                [$name, $variant] = self::parseIconArguments($arguments, $defaultVariant);

                return $icons->render($name, $variant, 'heroicons');
            });
        }
    }

    /**
     * @param  array<int|string, mixed>  $arguments
     * @return array{string, string, string|null}
     */
    private static function parseIconArguments(array $arguments, string $defaultVariant): array
    {
        $rawName = $arguments[0] ?? '';
        $name = is_scalar($rawName) ? (string) $rawName : '';
        $variantArg = $arguments['variant'] ?? null;
        $variant = is_string($variantArg) ? $variantArg : $defaultVariant;
        $setArg = $arguments['set'] ?? null;
        $set = is_string($setArg) ? $setArg : null;

        return [$name, $variant, $set];
    }

    private function registerPublishing(): void
    {
        $config = [self::CONFIG => $this->app->configPath('laradocs.php')];
        $views = [self::VIEWS => $this->app->resourcePath('views/vendor/laradocs')];
        $lang = [self::LANG => $this->app->langPath('vendor/laradocs')];
        $assets = [self::DIST => $this->app->publicPath('vendor/laradocs')];
        $stubs = [self::STUBS => $this->app->basePath('stubs/laradocs')];

        $this->publishes($config, 'laradocs-config');
        $this->publishes($views, 'laradocs-views');
        $this->publishes($lang, 'laradocs-lang');
        $this->publishes($assets, 'laradocs-assets');
        $this->publishes($stubs, 'laradocs-stubs');
        $this->publishes(array_merge($config, $views, $lang, $assets, $stubs), 'laradocs-all');
    }

    private function registerCommands(): void
    {
        $this->commands([
            InstallCommand::class,
            LangCommand::class,
            MakeDocCommand::class,
            CheckCommand::class,
            LintCommand::class,
            CacheCommand::class,
            ClearCommand::class,
            IndexCommand::class,
            LoginCommand::class,
            DeployCommand::class,
            CloneProjectCommand::class,
            ConfigCommand::class,
            VersionsCommand::class,
            OpenApiCommand::class,
        ]);

        // `laradocs:cache` builds a sitemap whose URLs come from
        // `route('laradocs.*')`. Those names only exist when the package
        // owns the docs URL — when a consumer app sets `route.register`
        // to false to wire docs into its own routes, hooking the command
        // into `optimize` would throw RouteNotFoundException on every
        // deploy. The consumer is responsible for warming its own cache.
        if (Config::bool('laradocs.route.register', true)) {
            $this->optimizes('laradocs:cache', 'laradocs:clear');
        }
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

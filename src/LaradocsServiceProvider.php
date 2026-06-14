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
use Laradocs\Console\CloneProjectCommand;
use Laradocs\Console\ConfigCommand;
use Laradocs\Console\DeployCommand;
use Laradocs\Console\IndexCommand;
use Laradocs\Console\InstallCommand;
use Laradocs\Console\LoginCommand;
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
use Laradocs\Extensions\MermaidExtension;
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
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'laradocs');
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
                fn (): string => Config::string('laradocs.docs.path'),
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

    /**
     * The locale the docs fall back to when a visitor hasn't picked one.
     *
     * Resolution order:
     *   1. An explicit `laradocs.locale.default` (or LARADOCS_LOCALE).
     *   2. The host application's locale, when it has a translation directory.
     *   3. The first configured `available` locale.
     *   4. The application locale as a last resort.
     */
    public static function defaultLocale(): string
    {
        $configured = Config::nullableString('laradocs.locale.default');

        if ($configured !== null && $configured !== '') {
            return $configured;
        }

        /** @var array<string, mixed> $available */
        $available = Config::array('laradocs.locale.available');
        $appLocale = (string) app()->getLocale();

        if (array_key_exists($appLocale, $available)) {
            return $appLocale;
        }

        $first = array_key_first($available);

        return is_string($first) && $first !== '' ? $first : $appLocale;
    }

    /**
     * The locale to render the current docs request in.
     *
     * An explicit choice — the `?lang=` query parameter or the cookie it sets —
     * wins when it maps to a configured `available` locale; otherwise the
     * {@see self::defaultLocale()} is used. Unknown codes are ignored so the
     * query string can never force an untranslated locale.
     */
    public static function determineLocale(Request $request): string
    {
        /** @var array<string, mixed> $available */
        $available = Config::array('laradocs.locale.available');

        foreach ([$request->query('lang'), $request->cookie('laradocs_locale')] as $candidate) {
            if (is_string($candidate) && $candidate !== '' && array_key_exists($candidate, $available)) {
                return $candidate;
            }
        }

        return self::defaultLocale();
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

        // Runs before CodeBlockExtension so mermaid fences are claimed before
        // they would otherwise pick up a language label and copy button.
        if ($config['mermaid'] ?? true) {
            $extensions[] = new MermaidExtension(
                Config::string(
                    'laradocs.parser.mermaid.src',
                    'https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.esm.min.mjs',
                ),
            );
        }

        $extensions[] = new CodeBlockExtension;

        return $extensions;
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
            __DIR__ . '/../resources/lang' => $this->app->langPath('vendor/laradocs'),
        ], 'laradocs-lang');

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
            LoginCommand::class,
            DeployCommand::class,
            CloneProjectCommand::class,
            ConfigCommand::class,
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

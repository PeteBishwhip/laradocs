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
use Laradocs\Console\CheckCommand;
use Laradocs\Console\ClearCommand;
use Laradocs\Console\CloneProjectCommand;
use Laradocs\Console\ConfigCommand;
use Laradocs\Console\DeployCommand;
use Laradocs\Console\IndexCommand;
use Laradocs\Console\InstallCommand;
use Laradocs\Console\LintCommand;
use Laradocs\Console\LoginCommand;
use Laradocs\Console\MakeDocCommand;
use Laradocs\Contracts\DocumentLoader;
use Laradocs\Contracts\DocumentParser;
use Laradocs\Contracts\HtmlExtension;
use Laradocs\Contracts\MarkdownExtension;
use Laradocs\Contracts\MetadataResolver;
use Laradocs\Contracts\OgImageGenerator;
use Laradocs\Extensions\BladeComponentExtension;
use Laradocs\Extensions\CalloutExtension;
use Laradocs\Extensions\CodeBlockExtension;
use Laradocs\Extensions\HeadingAnchorExtension;
use Laradocs\Extensions\ImageExtension;
use Laradocs\Extensions\KatexExtension;
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
use Laradocs\Seo\TheOgImageGenerator;
use Laradocs\Support\Config;
use Laradocs\Support\Locale;
use Laradocs\Support\RateLimiterConfig;
use Laradocs\Support\Version;
use Laradocs\Support\VersionRegistry;
use Laradocs\Variables\VariableRegistry;
use Laravel\Scout\EngineManager;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
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

        // The authoritative version list: discovers, sorts and resolves
        // documentation versions once per request for every consumer.
        $this->app->singleton(VersionRegistry::class);
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

        // Runs after macros so `@docs()` calls and `{{ variables }}` nested in a
        // component's slot are expanded before the slot is captured.
        if ($config['components'] ?? true) {
            $extensions[] = new BladeComponentExtension($app->make(MacroRegistry::class));
        }

        if ($config['katex'] ?? true) {
            $extensions[] = new KatexExtension(
                Config::string('laradocs.parser.katex.js', 'https://cdn.jsdelivr.net/npm/katex@0.16/dist/katex.min.js'),
                Config::string('laradocs.parser.katex.css', 'https://cdn.jsdelivr.net/npm/katex@0.16/dist/katex.min.css'),
                Config::bool('laradocs.parser.katex.ssr'),
                Config::nullableString('laradocs.parser.katex.node_bin'),
            );
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

        if ($config['katex'] ?? true) {
            $extensions[] = new KatexExtension(
                Config::string('laradocs.parser.katex.js', 'https://cdn.jsdelivr.net/npm/katex@0.16/dist/katex.min.js'),
                Config::string('laradocs.parser.katex.css', 'https://cdn.jsdelivr.net/npm/katex@0.16/dist/katex.min.css'),
                Config::bool('laradocs.parser.katex.ssr'),
                Config::nullableString('laradocs.parser.katex.node_bin'),
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

        foreach (['alert', 'badge', 'button', 'callout', 'embed'] as $name) {
            if (! $macros->has($name)) {
                $macros->register($name, "laradocs::macros.{$name}");
            }
        }
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

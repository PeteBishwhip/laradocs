<?php

declare(strict_types=1);

namespace Laradocs\Routing;

use Illuminate\Contracts\Routing\Registrar;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Laradocs\Http\Controllers\ApiSearchController;
use Laradocs\Http\Controllers\ApiTreeController;
use Laradocs\Http\Controllers\ApiVersionsController;
use Laradocs\Http\Controllers\AssetController;
use Laradocs\Http\Controllers\DocsController;
use Laradocs\Http\Controllers\FeedController;
use Laradocs\Http\Controllers\LlmsFullTxtController;
use Laradocs\Http\Controllers\LlmsTxtController;
use Laradocs\Http\Controllers\LocaleConsentController;
use Laradocs\Http\Controllers\McpController;
use Laradocs\Http\Controllers\MediaController;
use Laradocs\Http\Controllers\OgImageController;
use Laradocs\Http\Controllers\RobotsController;
use Laradocs\Http\Controllers\SearchController;
use Laradocs\Http\Controllers\SitemapController;
use Laradocs\Http\Controllers\TagController;
use Laradocs\Http\Middleware\EnsureDocsEnabled;
use Laradocs\Http\Middleware\EnsureMcpAuthenticated;
use Laradocs\Http\Middleware\EnsureMcpEnabled;
use Laradocs\Http\Middleware\EnsureMediaSignature;
use Laradocs\Http\Middleware\SetDocsLocale;
use Laradocs\Http\Middleware\SetDocsVersion;
use Laradocs\Http\Middleware\ThrottleApiRequests;
use Laradocs\Support\Config;

final class DocumentRouter
{
    /**
     * Package-owned middleware applied to every docs route when the
     * `route.package_middleware` config key is absent. Mirrors the default
     * shipped in config/laradocs.php.
     *
     * @var list<class-string>
     */
    private const DEFAULT_PACKAGE_MIDDLEWARE = [
        EnsureDocsEnabled::class,
        SetDocsLocale::class,
        SetDocsVersion::class,
    ];

    /**
     * Forces SetDocsVersion to activate the default version in place rather
     * than consulting the `versions.unversioned` policy — see the `:render`
     * doc block on {@see SetDocsVersion}. Used by fixed-path artifact routes
     * that carry no `{path}` to resolve a version from and can't sensibly
     * redirect.
     */
    private const RENDER_DEFAULT_VERSION = SetDocsVersion::class . ':render';

    /**
     * Register the docs index and catch-all show routes using the package's
     * configured prefix, domain, middleware and route-name prefix.
     *
     * @param  array<array-key, mixed>  $config
     */
    public function register(Registrar $router, array $config): void
    {
        $baseMiddleware = (array) ($config['middleware'] ?? ['web']);
        $packageMiddleware = (array) ($config['package_middleware'] ?? self::DEFAULT_PACKAGE_MIDDLEWARE);
        $middleware = array_merge($baseMiddleware, $packageMiddleware);

        $attributes = [
            'prefix' => $config['prefix'] ?? 'docs',
            'as' => $config['name'] ?? 'laradocs.',
            'middleware' => $middleware,
        ];

        if (! empty($config['domain'])) {
            $attributes['domain'] = $config['domain'];
        }

        // robots.txt is registered without EnsureDocsEnabled so that crawlers
        // still receive a valid "Disallow: /" body when the docs are off, as
        // opposed to a 404 they might interpret as transient.
        $robotsAttributes = $attributes;
        $robotsAttributes['middleware'] = $baseMiddleware;

        $router->group($robotsAttributes, function (Registrar $router): void {
            $router->get('robots.txt', RobotsController::class)->name('robots');
        });

        $llms = Config::bool('laradocs.llms.enabled', true);

        // The llms.txt convention points at the domain root, but a docs package
        // has no business claiming a path outside its own prefix unasked: the
        // host app may already serve /llms.txt describing the whole product.
        // Opting in registers the same controller at the root as well.
        if ($llms && Config::bool('laradocs.llms.root', false)) {
            $rootAttributes = $attributes;
            unset($rootAttributes['prefix']);

            $router->group($rootAttributes, function (Registrar $router): void {
                $router->get('llms.txt', LlmsTxtController::class)
                    ->withoutMiddleware(SetDocsVersion::class)
                    ->name('llms.root');
            });
        }

        $router->group($attributes, function (Registrar $router) use ($llms): void {
            $router->get('/', [DocsController::class, 'index'])->name('index');
            // sitemap.xml and feed.xml carry no version segment, so the
            // unversioned-URL policy would otherwise 301 them to an HTML docs
            // page — see the `:render` doc block on SetDocsVersion. They still
            // need a version active to build their content from, so the
            // middleware is kept but forced to activate the default version
            // in place instead of consulting the policy.
            $router->get('sitemap.xml', SitemapController::class)
                ->withoutMiddleware(SetDocsVersion::class)
                ->middleware(self::RENDER_DEFAULT_VERSION)
                ->name('sitemap');
            $router->get('feed.xml', FeedController::class)
                ->withoutMiddleware(SetDocsVersion::class)
                ->middleware(self::RENDER_DEFAULT_VERSION)
                ->name('feed');

            // llms.txt describes the site as a whole, so it is version-
            // agnostic: SetDocsVersion is dropped for the same reason as the
            // asset route below, whose unversioned-URL policy would otherwise
            // 301-redirect a path that carries no version segment.
            if ($llms) {
                $router->get('llms.txt', LlmsTxtController::class)
                    ->withoutMiddleware(SetDocsVersion::class)
                    ->name('llms');

                // The full corpus is version-agnostic for the same reason as
                // llms.txt above, and additionally gated on llms.full since a
                // mid-size site's corpus runs to megabytes.
                if (Config::bool('laradocs.llms.full', false)) {
                    $router->get('llms-full.txt', LlmsFullTxtController::class)
                        ->withoutMiddleware(SetDocsVersion::class)
                        ->name('llms.full');
                }
            }
            // Bundled assets (laradocs.js / laradocs.css) are served by file
            // name and carry no version segment. SetDocsVersion is dropped so
            // its unversioned-URL policy cannot 301-redirect the asset to a
            // default version — which would otherwise break every page's CSS
            // and JS the moment versioning is enabled with unversioned=redirect.
            $router->get('_laradocs/asset/{file}', AssetController::class)
                ->where('file', '[\w.\-]+')
                ->withoutMiddleware(SetDocsVersion::class)
                ->name('asset');
            // Files that sit beside the markdown: images, video, downloads.
            // Registered before the catch-all so a media path is never mistaken
            // for a document slug, and without SetDocsVersion for the same
            // reason as the assets above. `media.signed` adds the signature
            // check that stops the URL working anywhere but here.
            $router->get('_media/{path}', MediaController::class)
                ->where('path', '.*')
                ->withoutMiddleware(SetDocsVersion::class)
                ->middleware(EnsureMediaSignature::class)
                ->name('media');
            // The search endpoint carries no version segment either, same
            // reasoning as sitemap.xml above: force the default version rather
            // than 301-redirecting the palette's fetch() to an HTML page.
            $router->get('_laradocs/search', SearchController::class)
                ->withoutMiddleware(SetDocsVersion::class)
                ->middleware(self::RENDER_DEFAULT_VERSION)
                ->name('search');
            // Lets a consent banner's JS persist (or drop) the locale cookie the
            // instant the visitor's decision changes, via fetch() — no full-page
            // navigation required. SetDocsVersion is dropped for the same reason
            // as the asset route above: this isn't a versioned doc page.
            $router->get('_laradocs/consent', LocaleConsentController::class)
                ->middleware(ThrottleApiRequests::class)
                ->withoutMiddleware(SetDocsVersion::class)
                ->name('consent');

            if (Config::bool('laradocs.seo.og_image.enabled', true)) {
                // The index card has no {path} to resolve a version from,
                // same reasoning as sitemap.xml above; the {path} variant
                // below is untouched since it already carries one.
                $router->get('_laradocs/og', OgImageController::class)
                    ->withoutMiddleware(SetDocsVersion::class)
                    ->middleware(self::RENDER_DEFAULT_VERSION)
                    ->name('og.index');
                $router->get('_laradocs/og/{path}', OgImageController::class)
                    ->where('path', '.+')
                    ->name('og');
            }
            // Both JSON APIs carry no version segment either, same reasoning
            // as sitemap.xml above: force the default version rather than
            // 301-redirecting a documented integration surface to an HTML page.
            $router->get('_laradocs/api/tree', ApiTreeController::class)
                ->middleware(ThrottleApiRequests::class)
                ->withoutMiddleware(SetDocsVersion::class)
                ->middleware(self::RENDER_DEFAULT_VERSION)
                ->name('api.tree');
            $router->get('_laradocs/api/search', ApiSearchController::class)
                ->middleware(ThrottleApiRequests::class)
                ->withoutMiddleware(SetDocsVersion::class)
                ->middleware(self::RENDER_DEFAULT_VERSION)
                ->name('api.search');
            // The versions endpoint lists every version, so it is version-
            // agnostic: SetDocsVersion is dropped to stop its unversioned-URL
            // policy from redirecting this flat API route to a default version.
            $router->get('_laradocs/api/versions', ApiVersionsController::class)
                ->middleware(ThrottleApiRequests::class)
                ->withoutMiddleware(SetDocsVersion::class)
                ->name('api.versions');

            // POST /mcp → MCP JSON-RPC server. GET /mcp falls through to the
            // catch-all below, which renders mcp.md as a normal doc page when
            // that file exists — giving browsers the human-readable guide while
            // AI clients that POST application/json get the protocol server.
            $router->post('mcp', McpController::class)
                ->middleware([EnsureMcpEnabled::class, EnsureMcpAuthenticated::class, ThrottleApiRequests::class])
                ->withoutMiddleware([
                    VerifyCsrfToken::class,
                    PreventRequestForgery::class,
                ])
                ->name('mcp');

            // Tag index pages are registered ahead of the catch-all show route
            // so their fixed paths take priority; the controller still defers
            // to a real document occupying the same slug. Single-segment
            // {tag} keeps the catch-all responsible for any deeper paths.
            if (Config::bool('laradocs.tags.enabled', true)) {
                $index = trim(Config::string('laradocs.tags.index', 'tags'), '/');
                $prefix = trim(Config::string('laradocs.tags.prefix', 'tag'), '/');

                // Tag pages list version-scoped content but, like sitemap.xml
                // above, carry no version segment of their own: they render
                // the default version's tags rather than 301-redirecting.
                $router->get($index, [TagController::class, 'index'])
                    ->withoutMiddleware(SetDocsVersion::class)
                    ->middleware(self::RENDER_DEFAULT_VERSION)
                    ->name('tags.index');
                $router->get($prefix . '/{tag}', [TagController::class, 'show'])
                    ->where('tag', '[^/]+')
                    ->withoutMiddleware(SetDocsVersion::class)
                    ->middleware(self::RENDER_DEFAULT_VERSION)
                    ->name('tags.show');
            }

            $router->get('/{path}', [DocsController::class, 'show'])
                ->where('path', '.*')
                ->name('show');
        });
    }
}

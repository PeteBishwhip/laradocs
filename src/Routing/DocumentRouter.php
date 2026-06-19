<?php

declare(strict_types=1);

namespace Laradocs\Routing;

use Illuminate\Contracts\Routing\Registrar;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Laradocs\Http\Controllers\ApiSearchController;
use Laradocs\Http\Controllers\ApiTreeController;
use Laradocs\Http\Controllers\AssetController;
use Laradocs\Http\Controllers\DocsController;
use Laradocs\Http\Controllers\FeedController;
use Laradocs\Http\Controllers\McpController;
use Laradocs\Http\Controllers\OgImageController;
use Laradocs\Http\Controllers\RobotsController;
use Laradocs\Http\Controllers\SearchController;
use Laradocs\Http\Controllers\SitemapController;
use Laradocs\Http\Controllers\TagController;
use Laradocs\Http\Middleware\EnsureDocsEnabled;
use Laradocs\Http\Middleware\EnsureMcpAuthenticated;
use Laradocs\Http\Middleware\EnsureMcpEnabled;
use Laradocs\Http\Middleware\SetDocsLocale;
use Laradocs\Http\Middleware\SetDocsVersion;
use Laradocs\Http\Middleware\ThrottleApiRequests;
use Laradocs\Support\Config;

final class DocumentRouter
{
    /**
     * Register the docs index and catch-all show routes using the package's
     * configured prefix, domain, middleware and route-name prefix.
     *
     * @param  array<array-key, mixed>  $config
     */
    public function register(Registrar $router, array $config): void
    {
        $baseMiddleware = (array) ($config['middleware'] ?? ['web']);
        $middleware = array_merge($baseMiddleware, [EnsureDocsEnabled::class, SetDocsLocale::class, SetDocsVersion::class]);

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

        $router->group($attributes, function (Registrar $router): void {
            $router->get('/', [DocsController::class, 'index'])->name('index');
            $router->get('sitemap.xml', SitemapController::class)->name('sitemap');
            $router->get('feed.xml', FeedController::class)->name('feed');
            $router->get('_laradocs/asset/{file}', AssetController::class)
                ->where('file', '[\w.\-]+')
                ->name('asset');
            $router->get('_laradocs/search', SearchController::class)->name('search');

            if (Config::bool('laradocs.seo.og_image.enabled', true)) {
                $router->get('_laradocs/og', OgImageController::class)->name('og.index');
                $router->get('_laradocs/og/{path}', OgImageController::class)
                    ->where('path', '.+')
                    ->name('og');
            }
            $router->get('_laradocs/api/tree', ApiTreeController::class)
                ->middleware(ThrottleApiRequests::class)
                ->name('api.tree');
            $router->get('_laradocs/api/search', ApiSearchController::class)
                ->middleware(ThrottleApiRequests::class)
                ->name('api.search');

            $router->post('_laradocs/mcp', McpController::class)
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

                $router->get($index, [TagController::class, 'index'])->name('tags.index');
                $router->get($prefix . '/{tag}', [TagController::class, 'show'])
                    ->where('tag', '[^/]+')
                    ->name('tags.show');
            }

            $router->get('/{path}', [DocsController::class, 'show'])
                ->where('path', '.*')
                ->name('show');
        });
    }
}

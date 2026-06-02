<?php

declare(strict_types=1);

namespace Laradocs\Routing;

use Illuminate\Contracts\Routing\Registrar;
use Laradocs\Http\Controllers\AssetController;
use Laradocs\Http\Controllers\DocsController;
use Laradocs\Http\Middleware\EnsureDocsEnabled;

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
        $middleware = array_merge(
            (array) ($config['middleware'] ?? ['web']),
            [EnsureDocsEnabled::class],
        );

        $attributes = [
            'prefix' => $config['prefix'] ?? 'docs',
            'as' => $config['name'] ?? 'laradocs.',
            'middleware' => $middleware,
        ];

        if (! empty($config['domain'])) {
            $attributes['domain'] = $config['domain'];
        }

        $router->group($attributes, function (Registrar $router): void {
            $router->get('/', [DocsController::class, 'index'])->name('index');
            $router->get('_laradocs/asset/{file}', AssetController::class)
                ->where('file', '[\w.\-]+')
                ->name('asset');
            $router->get('/{path}', [DocsController::class, 'show'])
                ->where('path', '.*')
                ->name('show');
        });
    }
}

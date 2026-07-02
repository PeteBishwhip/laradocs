<?php

declare(strict_types=1);

namespace Laradocs\OpenApi\Generator;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Laradocs\Contracts\OpenApiSpecGenerator;
use Laradocs\OpenApi\OpenApiException;

/**
 * Structural shape for Scramble's per-API registration handle.
 *
 * Scramble is an optional dependency, so its real `Dedoc\Scramble\Scramble`
 * registration object is never imported. This `@internal` interface exists only
 * to describe — for static analysis — the single method the adapter calls on the
 * value returned by `Dedoc\Scramble\Scramble::registerApi()`. Nothing implements
 * it at runtime; it lives purely in a docblock type hint.
 *
 * @internal
 */
interface ScrambleApiRegistration
{
    /**
     * @param  callable(Route): bool  $resolver
     */
    public function routes(callable $resolver): self;
}

/**
 * Structural shape for Scramble's invokable document generator.
 *
 * Describes — for static analysis only — the surface the adapter touches on the
 * container-resolved `Dedoc\Scramble\Generator`: toggling exception rethrowing
 * and invoking it to produce the document. As with {@see ScrambleApiRegistration}
 * nothing implements this at runtime; it is a docblock-only hint that keeps the
 * package free of hard references to the optional Scramble classes.
 *
 * @internal
 */
interface ScrambleDocumentGenerator
{
    public function setThrowExceptions(bool $throw): self;

    /**
     * @return array<string, mixed>
     */
    public function __invoke(object $config): array;
}

/**
 * Adapts dedoc/scramble's generator to the {@see OpenApiSpecGenerator} contract
 * so it can stand in for the built-in {@see SpecGenerator} behind
 * {@see SpecGeneratorFactory}.
 *
 * Scramble is an optional dependency: both `Dedoc\Scramble\Scramble` and
 * `Dedoc\Scramble\Generator` are referenced by string and resolved at call time
 * (static utility / container binding) rather than imported, so this file loads
 * cleanly on hosts that do not have the package. {@see generate()} still guards
 * on the package being present and throws the same message the factory uses.
 *
 * Route selection reuses the exact prefix + middleware filter that
 * {@see RouteCollector::matches()} applies, so the Scramble-backed document
 * covers the same surface the native generator would.
 */
final class ScrambleSpecGenerator implements OpenApiSpecGenerator
{
    /**
     * Static utility used to register a named API and its route resolver.
     */
    private const SCRAMBLE = 'Dedoc\Scramble\Scramble';

    /**
     * Container binding for Scramble's invokable document generator.
     */
    private const GENERATOR = 'Dedoc\Scramble\Generator';

    /**
     * Name the API is registered under with Scramble.
     */
    private const API = 'laradocs';

    /**
     * @param  array<int|string, mixed>  $security
     */
    public function __construct(
        private readonly Router $router,
        private readonly string $title = 'API',
        private readonly string $version = '1.0.0',
        private readonly ?string $serverUrl = null,
        private readonly ?string $description = null,
        private readonly array $security = [],
        private readonly ?string $prefix = 'api',
        private readonly ?string $middleware = 'api',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generate(): array
    {
        // @codeCoverageIgnoreStart
        // Everything from here on delegates to dedoc/scramble, an optional
        // dependency that is absent from CI, so these lines cannot be executed
        // (nor meaningfully asserted) without installing the package. The
        // equivalent Scramble-missing behaviour is covered at the factory level
        // by OpenApiGeneratorFactoryTest; this adapter's constructor is covered
        // when the factory builds it with Scramble marked available.
        if (! class_exists('\Dedoc\Scramble\Generator')) {
            throw new OpenApiException(SpecGeneratorFactory::MISSING_MESSAGE);
        }

        $routes = $this->matchingRoutes();

        $scramble = self::SCRAMBLE;

        /** @var ScrambleApiRegistration $api */
        $api = $scramble::registerApi(self::API, $this->config());
        $api->routes(static fn (Route $route): bool => in_array($route, $routes, true));

        /** @var ScrambleDocumentGenerator $generator */
        $generator = app(self::GENERATOR);
        $generator->setThrowExceptions(false);

        return $this->finalize($generator($api));
    }

    /**
     * The application's registered routes reduced to the ones Scramble should
     * document — the same subset {@see RouteCollector} would collect for the
     * injected router.
     *
     * @return array<int, Route>
     */
    private function matchingRoutes(): array
    {
        $matching = [];

        /** @var array<int, Route> $routes */
        $routes = $this->router->getRoutes()->getRoutes();

        foreach ($routes as $route) {
            if ($this->matches($route)) {
                $matching[] = $route;
            }
        }

        return $matching;
    }

    /**
     * Duplicated from {@see RouteCollector::matches()} (which is private): keep a
     * route only when it clears the optional prefix and middleware filters.
     */
    private function matches(Route $route): bool
    {
        if ($this->prefix !== null && $this->prefix !== '') {
            $needle = trim($this->prefix, '/');

            if (! str_starts_with(trim($route->uri(), '/'), $needle)) {
                return false;
            }
        }

        if ($this->middleware !== null && $this->middleware !== '' && ! in_array($this->middleware, $route->gatherMiddleware(), true)) {
            return false;
        }

        return true;
    }

    /**
     * Translate the resolved config into the shape Scramble's registration accepts.
     *
     * @return array<string, mixed>
     */
    private function config(): array
    {
        $info = ['title' => $this->title, 'version' => $this->version];

        if ($this->description !== null && $this->description !== '') {
            $info['description'] = $this->description;
        }

        $config = ['info' => $info];

        if ($this->serverUrl !== null && $this->serverUrl !== '') {
            $config['servers'] = [['url' => rtrim($this->serverUrl, '/')]];
        }

        return $config;
    }

    /**
     * Post-process Scramble's document: force the title to the configured value
     * regardless of how Scramble resolved its own, and overlay any document-level
     * security requirement.
     *
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    private function finalize(array $spec): array
    {
        if (! isset($spec['info']) || ! is_array($spec['info'])) {
            $spec['info'] = [];
        }

        $spec['info']['title'] = $this->title;

        if ($this->security !== []) {
            $spec['security'] = $this->security;
        }

        return $spec;
        // @codeCoverageIgnoreEnd
    }
}

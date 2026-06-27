<?php

declare(strict_types=1);

namespace Laradocs\OpenApi\Generator;

/**
 * A single application route reduced to the handful of facts the spec
 * generator needs: which HTTP verbs it answers, the normalised URI (with
 * Laravel `{param}` placeholders intact), the path-parameter names, and the
 * controller class/method backing it (null for closure routes).
 *
 * Plain scalars/arrays only, so it stays trivially serialisable and
 * static-analysis clean.
 */
final class CollectedRoute
{
    /**
     * @param  array<int, string>  $methods  Upper-cased HTTP verbs (HEAD/OPTIONS stripped).
     * @param  array<int, string>  $pathParameters  Names of `{param}` placeholders in the URI.
     */
    public function __construct(
        public readonly array $methods,
        public readonly string $uri,
        public readonly array $pathParameters,
        public readonly ?string $controller = null,
        public readonly ?string $action = null,
        public readonly ?string $name = null,
    ) {}
}

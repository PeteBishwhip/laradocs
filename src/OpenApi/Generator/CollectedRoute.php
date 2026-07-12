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
     * @var array<int, string>
     * @readonly
     */
    public $methods;
    /**
     * @readonly
     * @var string
     */
    public $uri;
    /**
     * @var array<int, string>
     * @readonly
     */
    public $pathParameters;
    /**
     * @readonly
     * @var string|null
     */
    public $controller;
    /**
     * @readonly
     * @var string|null
     */
    public $action;
    /**
     * @readonly
     * @var string|null
     */
    public $name;
    /**
     * @param  array<int, string>  $methods  Upper-cased HTTP verbs (HEAD/OPTIONS stripped).
     * @param  array<int, string>  $pathParameters  Names of `{param}` placeholders in the URI.
     */
    public function __construct(array $methods, string $uri, array $pathParameters, ?string $controller = null, ?string $action = null, ?string $name = null)
    {
        $this->methods = $methods;
        $this->uri = $uri;
        $this->pathParameters = $pathParameters;
        $this->controller = $controller;
        $this->action = $action;
        $this->name = $name;
    }
}

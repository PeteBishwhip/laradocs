<?php

declare(strict_types=1);

namespace Laradocs\OpenApi\Generator;

use Illuminate\Foundation\Http\FormRequest;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * Infers a request's input schema for a {@see CollectedRoute} by reflecting the
 * controller action.
 *
 * Two sources are consulted, in order of reliability:
 *   1. A type-hinted {@see FormRequest} parameter — its `rules()` array is the
 *      authoritative input contract.
 *   2. A detectable inline `$request->validate([...])` / `$this->validate(...)`
 *      call in the action body — scraped from the method source as a fallback.
 *
 * Each field's ruleset is run through {@see RuleMapper} to produce a JSON
 * Schema object (`properties` + `required`). An empty result means "no
 * inferable input".
 */
final class RequestInspector
{
    /**
     * @readonly
     * @var \Laradocs\OpenApi\Generator\RuleMapper
     */
    private $mapper;
    public function __construct(?RuleMapper $mapper = null)
    {
        $mapper = $mapper ?? new RuleMapper;
        $this->mapper = $mapper;
    }

    /**
     * @return array{properties: array<string, array<string, mixed>>, required: array<int, string>}
     */
    public function inspect(CollectedRoute $route): array
    {
        $method = $this->reflect($route);

        if ($method === null) {
            return ['properties' => [], 'required' => []];
        }

        $rules = $this->formRequestRules($method) ?? $this->inlineRules($method);

        return $this->toSchema($rules);
    }

    private function reflect(CollectedRoute $route): ?ReflectionMethod
    {
        if ($route->controller === null || $route->action === null) {
            return null;
        }

        if (! method_exists($route->controller, $route->action)) {
            return null;
        }

        $reflection = new ReflectionMethod($route->controller, $route->action);
        if (PHP_VERSION_ID < 80100) {
            $reflection->setAccessible(true);
        }

        return $reflection;
    }

    /**
     * @return array<string, mixed>|null rules() keyed by field, or null when the action has no FormRequest.
     */
    private function formRequestRules(ReflectionMethod $method): ?array
    {
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $class = $type->getName();

            if (! is_subclass_of($class, FormRequest::class)) {
                continue;
            }

            return $this->resolveRules($class);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveRules(string $class): array
    {
        try {
            /** @var FormRequest $request */
            $request = new $class;

            if (! method_exists($request, 'rules')) {
                return [];
            }

            /** @var array<string, mixed> */
            return $request->rules();
        } catch (Throwable $exception) {
            // rules() may depend on the route/container; treat as empty.
            return [];
        }
    }

    /**
     * Best-effort scrape of an inline `validate([...])` array literal from the
     * action source. Only simple `'field' => 'rule|rule'` string pairs are
     * recovered; anything more dynamic is silently skipped.
     *
     * @return array<string, mixed>
     */
    private function inlineRules(ReflectionMethod $method): array
    {
        $source = MethodSource::read($method) ?? '';

        if (! preg_match('/validate\s*\(\s*\[/', $source)) {
            return [];
        }

        $rules = [];

        // Capture 'field' => 'rules' / "field" => "rules" pairs.
        if (preg_match_all(
            '/([\'"])([^\'"]+)\1\s*=>\s*([\'"])([^\'"]*)\3/',
            $source,
            $matches,
            PREG_SET_ORDER,
        )) {
            foreach ($matches as $match) {
                $rules[$match[2]] = $match[4];
            }
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array{properties: array<string, array<string, mixed>>, required: array<int, string>}
     */
    private function toSchema(array $rules): array
    {
        $properties = [];
        $required = [];

        foreach ($rules as $field => $ruleset) {
            if (! is_string($ruleset) && ! is_array($ruleset)) {
                continue;
            }

            $name = $this->mapper->propertyName((string) $field);

            if ($name === '' || isset($properties[$name])) {
                continue;
            }

            $mapped = $this->mapper->map($ruleset);

            $properties[$name] = $mapped['schema'];

            if ($mapped['required']) {
                $required[] = $name;
            }
        }

        return ['properties' => $properties, 'required' => array_values(array_unique($required))];
    }
}

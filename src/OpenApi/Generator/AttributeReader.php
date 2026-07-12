<?php

declare(strict_types=1);

namespace Laradocs\OpenApi\Generator;

use Laradocs\OpenApi\Generator\Attributes\ApiOperation;
use ReflectionMethod;

/**
 * Reads explicit operation overrides off a {@see CollectedRoute}'s controller
 * action — the escape hatch for everything inference cannot recover.
 *
 * Two sources are consulted, in increasing order of precedence:
 *   1. The action's docblock — its first sentence becomes the summary, the rest
 *      the description, and a `@deprecated` tag flags the operation deprecated.
 *   2. An {@see ApiOperation} attribute on the action — any property it sets
 *      wins over both the docblock and the generator's inferred value.
 *
 * The returned array only carries the keys that were actually overridden, so the
 * generator can apply them with a simple `$override ?? $inferred` per field.
 */
final class AttributeReader
{
    /**
     * @return array{summary?: string, description?: string, operationId?: string, tags?: array<int, string>, deprecated?: bool}
     */
    public function read(CollectedRoute $route): array
    {
        $method = $this->reflect($route);

        if ($method === null) {
            return [];
        }

        $overrides = $this->fromDocblock($method);

        foreach ($this->fromAttribute($method) as $key => $value) {
            $overrides[$key] = $value;
        }

        return $overrides;
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
     * @return array{summary?: string, description?: string, deprecated?: bool}
     */
    private function fromDocblock(ReflectionMethod $method): array
    {
        $doc = $method->getDocComment();

        if ($doc === false) {
            return [];
        }

        $lines = [];
        $deprecated = false;

        foreach (preg_split('/\R/', $doc) ?: [] as $line) {
            $line = trim($line);
            $line = ltrim($line, '/* ');
            $line = rtrim($line, '*/ ');
            $line = trim($line);

            if (strncmp($line, '@deprecated', strlen('@deprecated')) === 0) {
                $deprecated = true;

                continue;
            }

            // Skip every other annotation tag and the bare delimiter lines.
            if ($line === '' || strncmp($line, '@', strlen('@')) === 0) {
                continue;
            }

            $lines[] = $line;
        }

        $overrides = [];

        if ($lines !== []) {
            $overrides['summary'] = array_shift($lines);

            $description = trim(implode(' ', $lines));

            if ($description !== '') {
                $overrides['description'] = $description;
            }
        }

        if ($deprecated) {
            $overrides['deprecated'] = true;
        }

        return $overrides;
    }

    /**
     * @return array{summary?: string, description?: string, operationId?: string, tags?: array<int, string>, deprecated?: bool}
     */
    private function fromAttribute(ReflectionMethod $method): array
    {
        $attributes = method_exists($method, 'getAttributes') ? $method->getAttributes(ApiOperation::class) : [];

        if ($attributes === []) {
            return [];
        }

        $operation = $attributes[0]->newInstance();

        $overrides = [];

        if ($operation->summary !== null && $operation->summary !== '') {
            $overrides['summary'] = $operation->summary;
        }

        if ($operation->description !== null && $operation->description !== '') {
            $overrides['description'] = $operation->description;
        }

        if ($operation->operationId !== null && $operation->operationId !== '') {
            $overrides['operationId'] = $operation->operationId;
        }

        $tags = array_values(array_filter(
            $operation->tags,
            static function (string $tag): bool {
                return $tag !== '';
            },
        ));

        if ($tags !== []) {
            $overrides['tags'] = $tags;
        }

        if ($operation->deprecated) {
            $overrides['deprecated'] = true;
        }

        return $overrides;
    }
}

<?php

declare(strict_types=1);

namespace Laradocs\Variables;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Support\Arr;

final class VariableRegistry
{
    /**
     * Eagerly defined values.
     *
     * @var array<string, mixed>
     */
    private array $items = [];

    /**
     * Deferred providers resolved each time variables are read.
     *
     * @var array<int, Closure>
     */
    private array $deferred = [];

    /**
     * @param  array<string, mixed>  $items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Set a single value (scalar, array, or closure).
     */
    public function set(string $key, mixed $value): self
    {
        $this->items[$key] = $value;

        return $this;
    }

    /**
     * Register more variables, either as an array or a closure returning one.
     *
     * @param  array<string, mixed>|Closure  $values
     */
    public function register(array|Closure $values): self
    {
        if ($values instanceof Closure) {
            $this->deferred[] = $values;
        } else {
            $this->items = array_merge($this->items, $values);
        }

        return $this;
    }

    public function has(string $key): bool
    {
        return Arr::has($this->all(), $key);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->all(), $key, $default);
    }

    /**
     * The fully resolved variable map, with closures invoked.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $resolved = $this->resolveValues($this->items);

        foreach ($this->deferred as $provider) {
            /** @var array<string, mixed> $values */
            $values = Container::getInstance()->call($provider);
            $resolved = array_merge($resolved, $this->resolveValues($values));
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function resolveValues(array $values): array
    {
        return array_map(function (mixed $value): mixed {
            return $value instanceof Closure
                ? Container::getInstance()->call($value)
                : $value;
        }, $values);
    }
}

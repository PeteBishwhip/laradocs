<?php

declare(strict_types=1);

namespace Laradocs\Macros;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Laradocs\Exceptions\UnknownMacroException;

final class MacroRegistry
{
    /**
     * @var array<string, Closure|string>
     */
    private $macros = [];

    /**
     * @param  array<string, Closure|string>  $macros
     */
    public function __construct(array $macros = [])
    {
        $this->macros = $macros;
    }

    /**
     * Register a macro by name. Handlers are either a closure returning HTML
     * or the name of a Blade view to render with the supplied arguments.
     *
     * **Boot-time only.** This mutates a singleton; call it exclusively from a
     * service provider's `boot()` method. Registering macros during request
     * processing causes them to accumulate across requests on long-lived
     * workers such as Laravel Octane.
     * @param \Closure|string $handler
     */
    public function register(string $name, $handler): self
    {
        $this->macros[$name] = $handler;

        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->macros[$name]);
    }

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_keys($this->macros);
    }

    /**
     * Render a macro to an HTML string.
     *
     * @param  array<array-key, mixed>  $arguments
     */
    public function render(string $name, array $arguments = []): string
    {
        if (! $this->has($name)) {
            throw UnknownMacroException::for($name);
        }

        $handler = $this->macros[$name];

        if ($handler instanceof Closure) {
            $named = ['arguments' => $arguments];

            foreach ($arguments as $key => $value) {
                if (is_string($key)) {
                    $named[$key] = $value;
                }
            }

            $result = Container::getInstance()->call($handler, $named);

            return is_scalar($result) ? (string) $result : '';
        }

        /** @var ViewFactory $factory */
        $factory = Container::getInstance()->make(ViewFactory::class);

        return $factory->make($handler, $arguments + ['arguments' => $arguments])->render();
    }
}

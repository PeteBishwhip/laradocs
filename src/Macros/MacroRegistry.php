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
    private array $macros = [];

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
     */
    public function register(string $name, Closure|string $handler): self
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

        // Only string keys can become Blade variables — the view's own
        // extract() skips numeric ones — so narrowing to them here loses
        // nothing and gives the factory the array<string, mixed> it declares.
        $data = ['arguments' => $arguments];

        foreach ($arguments as $key => $value) {
            if (is_string($key)) {
                $data[$key] = $value;
            }
        }

        // The view name comes from user configuration, so it cannot be known
        // to be a registered view at analysis time.
        /** @var view-string $handler */
        return $factory->make($handler, $data)->render();
    }
}

<?php

declare(strict_types=1);

namespace Laradocs\Icons;

use Closure;

/**
 * Resolves icon names to inline SVG HTML strings.
 *
 * Multiple icon sets can be registered under unique names. A default set is
 * used when no set is specified in an @icon() call or macro invocation.
 */
final class IconRegistry
{
    /** @var array<string, Closure(string, string): string> */
    private array $sets = [];

    public function __construct(
        private readonly string $defaultSet = 'heroicons',
    ) {}

    /**
     * Register an icon set. The provider receives the icon name and variant
     * and must return a raw SVG string (without wrapper element), or an empty
     * string when the icon is not found.
     *
     * @param  Closure(string, string): string  $provider
     */
    public function register(string $name, Closure $provider): self
    {
        $this->sets[$name] = $provider;

        return $this;
    }

    public function has(string $set): bool
    {
        return isset($this->sets[$set]);
    }

    public function getDefaultSet(): string
    {
        return $this->defaultSet;
    }

    /**
     * Render an icon name to an HTML string.
     *
     * Returns an empty string when the set is not registered or the icon is
     * not found. The SVG is wrapped in a <span class="laradocs-icon"> so it
     * can be styled independently of the surrounding text.
     */
    public function render(string $icon, string $variant = 'outline', ?string $set = null): string
    {
        if ($icon === '') {
            return '';
        }

        $set ??= $this->defaultSet;

        if (! isset($this->sets[$set])) {
            return '';
        }

        $svg = ($this->sets[$set])($icon, $variant);

        if ($svg === '') {
            return '';
        }

        return '<span class="laradocs-icon" aria-hidden="true">' . $svg . '</span>';
    }
}

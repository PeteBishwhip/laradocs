<?php

declare(strict_types=1);

namespace Laradocs\Icons;

use Laradocs\Support\ValueCaster;

/**
 * A parsed icon invocation — the name, variant and (optional) set extracted
 * from the inner argument string of an `@icon(...)` call. Shared by the markdown
 * extension that renders icons and the linter that validates them, so the
 * argument grammar lives in exactly one place.
 */
final class IconReference
{
    public function __construct(
        public readonly string $name,
        public readonly string $variant,
        public readonly ?string $set,
    ) {}

    /**
     * Parse the inside of an `@icon(...)` call: a positional name followed by
     * optional `variant:` / `set:` named arguments.
     */
    public static function parse(string $inner, string $defaultVariant = 'outline'): self
    {
        $tokens = ValueCaster::tokenize($inner);

        if ($tokens === []) {
            return new self('', $defaultVariant, null);
        }

        $name = ValueCaster::unquote((string) array_shift($tokens));
        $variant = $defaultVariant;
        $set = null;

        foreach ($tokens as $token) {
            if (preg_match('/^([a-zA-Z_]\w*)\s*:\s*(.+)$/s', $token, $m) !== 1) {
                continue;
            }

            $value = ValueCaster::unquote(trim($m[2]));

            if ($m[1] === 'variant') {
                $variant = $value;
            } elseif ($m[1] === 'set') {
                $set = $value;
            }
        }

        return new self($name, $variant, $set);
    }
}

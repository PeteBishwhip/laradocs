<?php

declare(strict_types=1);

namespace Laradocs\Extensions;

use Laradocs\Contracts\MarkdownExtension;
use Laradocs\Icons\IconRegistry;
use Laradocs\Support\CodeAwareReplacer;
use Laradocs\Support\ValueCaster;

/**
 * Expands @icon('name') and @icon('name', variant: 'solid', set: 'heroicons')
 * shorthand into rendered icon HTML.
 *
 * This complement to the @docs('icon', ...) macro approach allows a more
 * natural inline syntax, matching common template engines.
 */
final class IconExtension implements MarkdownExtension
{
    public function __construct(
        private readonly IconRegistry $icons,
    ) {}

    public function processMarkdown(string $markdown): string
    {
        return CodeAwareReplacer::apply($markdown, fn (string $text): string => $this->expand($text));
    }

    private function expand(string $text): string
    {
        return (string) preg_replace_callback(
            '/@icon\(([^)]+)\)/',
            function (array $matches): string {
                [$name, $args] = $this->parseArgs($matches[1]);

                if ($name === '') {
                    return $matches[0];
                }

                $variant = is_string($args['variant'] ?? null) ? $args['variant'] : 'outline';
                $set = is_string($args['set'] ?? null) ? $args['set'] : null;

                return $this->icons->render($name, $variant, $set);
            },
            $text,
        );
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function parseArgs(string $inner): array
    {
        $tokens = [];
        $buffer = '';
        $quote = null;

        for ($i = 0, $len = strlen($inner); $i < $len; $i++) {
            $char = $inner[$i];

            if ($quote !== null) {
                $buffer .= $char;

                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $buffer .= $char;
            } elseif ($char === ',') {
                $tokens[] = trim($buffer);
                $buffer = '';
            } else {
                $buffer .= $char;
            }
        }

        if (trim($buffer) !== '') {
            $tokens[] = trim($buffer);
        }

        if ($tokens === []) {
            return ['', []];
        }

        $name = ValueCaster::unquote(array_shift($tokens));
        $args = [];

        foreach ($tokens as $token) {
            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*:\s*(.+)$/s', $token, $m) === 1) {
                $args[$m[1]] = ValueCaster::unquote(trim($m[2]));
            }
        }

        return [$name, $args];
    }
}

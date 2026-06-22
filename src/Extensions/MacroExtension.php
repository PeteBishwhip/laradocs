<?php

declare(strict_types=1);

namespace Laradocs\Extensions;

use Laradocs\Contracts\MarkdownExtension;
use Laradocs\Macros\MacroRegistry;
use Laradocs\Support\CodeAwareReplacer;
use Laradocs\Support\ValueCaster;

/**
 * Expands @docs('name', key: 'value', ...) calls into rendered macro HTML.
 */
final class MacroExtension implements MarkdownExtension
{
    public function __construct(
        private readonly MacroRegistry $macros,
    ) {}

    public function processMarkdown(string $markdown): string
    {
        return CodeAwareReplacer::apply($markdown, fn (string $text): string => $this->expand($text));
    }

    private function expand(string $text): string
    {
        $result = '';
        $offset = 0;
        $length = strlen($text);

        while (($start = strpos($text, '@docs(', $offset)) !== false) {
            $result .= substr($text, $offset, $start - $offset);

            $close = $this->matchingParen($text, $start + strlen('@docs('));

            if ($close === null) {
                // No balanced close paren — emit verbatim and stop scanning.
                $result .= substr($text, $start);

                return $result;
            }

            $inner = substr($text, $start + strlen('@docs('), $close - ($start + strlen('@docs(')));
            $result .= $this->renderCall($inner);
            $offset = $close + 1;
        }

        $result .= substr($text, $offset, $length - $offset);

        return $result;
    }

    private function renderCall(string $inner): string
    {
        [$name, $arguments] = $this->parseArguments($inner);

        if ($name === '' || ! $this->macros->has($name)) {
            return '';
        }

        return $this->macros->render($name, $arguments);
    }

    private function matchingParen(string $text, int $from): ?int
    {
        $depth = 1;
        $quote = null;
        $len = strlen($text);

        for ($i = $from; $i < $len; $i++) {
            $char = $text[$i];

            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;

                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * @return array{0: string, 1: array<array-key, mixed>}
     */
    private function parseArguments(string $inner): array
    {
        $tokens = $this->tokenize($inner);

        if ($tokens === []) {
            return ['', []];
        }

        $name = ValueCaster::unquote(array_shift($tokens));
        $arguments = [];
        $position = 0;

        foreach ($tokens as $token) {
            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*:\s*(.*)$/s', $token, $m) === 1) {
                $arguments[$m[1]] = ValueCaster::cast($m[2]);
            } else {
                $arguments[$position++] = ValueCaster::cast($token);
            }
        }

        return [$name, $arguments];
    }

    /**
     * Split a macro arg list on commas, respecting quotes.
     *
     * @return array<int, string>
     */
    private function tokenize(string $inner): array
    {
        return ValueCaster::tokenize($inner);
    }
}

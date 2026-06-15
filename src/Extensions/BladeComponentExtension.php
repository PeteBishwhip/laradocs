<?php

declare(strict_types=1);

namespace Laradocs\Extensions;

use Laradocs\Contracts\MarkdownExtension;
use Laradocs\Macros\MacroRegistry;
use Laradocs\Support\CodeAwareReplacer;
use Laradocs\Support\ValueCaster;

/**
 * Renders Blade-component-style tags — `<x-name attr="value">…</x-name>` — that
 * authors drop straight into their markdown.
 *
 * The component name is resolved against the {@see MacroRegistry}, which doubles
 * as the whitelist: only registered names render, and they render through the
 * very same engine as `@docs('name', ...)`, so the two syntaxes round-trip.
 * No arbitrary Blade is ever compiled — attribute values are treated as plain
 * literals (`:bound="expr"` included), never evaluated as PHP.
 *
 * To show a component tag literally, escape the opening bracket with a
 * backslash (`\<x-callout>`) or wrap it in a code span / fenced block.
 */
final class BladeComponentExtension implements MarkdownExtension
{
    private const PREFIX = 'x-';

    public function __construct(
        private readonly MacroRegistry $macros,
    ) {}

    public function processMarkdown(string $markdown): string
    {
        // Mask code first so tags inside code samples are documented literally,
        // then expand across the whole document (block tags may span lines).
        [$masked, $restore] = CodeAwareReplacer::protect($markdown);

        return $restore($this->expand($masked));
    }

    private function expand(string $text): string
    {
        $needle = '<' . self::PREFIX;
        $result = '';
        $offset = 0;

        while (($lt = strpos($text, $needle, $offset)) !== false) {
            $result .= substr($text, $offset, $lt - $offset);
            $offset = $lt;

            // Backslash-escaped — leave the tag (and its backslash) in place so
            // CommonMark renders the literal angle bracket as text.
            if ($lt > 0 && $text[$lt - 1] === '\\') {
                $result .= $needle;
                $offset += strlen($needle);

                continue;
            }

            $tag = $this->parseOpeningTag($text, $lt);

            if ($tag === null) {
                $result .= '<';
                $offset++;

                continue;
            }

            $end = $tag['end'];
            $slot = null;

            if (! $tag['selfClosing']) {
                $closeStart = $this->findClosingTag($text, $tag['name'], $tag['end']);

                if ($closeStart === null) {
                    // No matching close — treat the opening tag as literal text.
                    $result .= substr($text, $lt, $tag['end'] - $lt);
                    $offset = $tag['end'];

                    continue;
                }

                $slot = substr($text, $tag['end'], $closeStart - $tag['end']);
                $end = $closeStart + strlen($this->closeTag($tag['name']));
            }

            if ($this->macros->has($tag['name'])) {
                $arguments = $tag['attributes'];

                if ($slot !== null) {
                    // Expand the slot first so nested components render too.
                    $arguments['slot'] = trim($this->expand($slot));
                }

                $result .= $this->macros->render($tag['name'], $arguments);
            } else {
                // Not whitelisted — pass the original source through untouched.
                $result .= substr($text, $lt, $end - $lt);
            }

            $offset = $end;
        }

        return $result . substr($text, $offset);
    }

    /**
     * Parse the opening `<x-name ...>` (or self-closing `<x-name ... />`) tag
     * that begins at $lt. Returns null when the text is not a well-formed tag.
     *
     * @return array{name: string, attributes: array<string, mixed>, selfClosing: bool, end: int}|null
     */
    private function parseOpeningTag(string $text, int $lt): ?array
    {
        $length = strlen($text);
        $i = $lt + strlen('<' . self::PREFIX);

        $nameStart = $i;
        while ($i < $length && preg_match('/[A-Za-z0-9._-]/', $text[$i]) === 1) {
            $i++;
        }

        $name = substr($text, $nameStart, $i - $nameStart);

        if ($name === '' || ! $this->isBoundary($text, $i)) {
            return null;
        }

        // Scan to the closing '>', honouring quoted attribute values.
        $attrStart = $i;
        $quote = null;

        while ($i < $length) {
            $char = $text[$i];

            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                }
            } elseif ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '>') {
                break;
            }

            $i++;
        }

        if ($i >= $length) {
            return null;
        }

        $rawAttributes = rtrim(substr($text, $attrStart, $i - $attrStart));
        $selfClosing = false;

        if (str_ends_with($rawAttributes, '/')) {
            $selfClosing = true;
            $rawAttributes = substr($rawAttributes, 0, -1);
        }

        return [
            'name' => $name,
            'attributes' => $this->parseAttributes($rawAttributes),
            'selfClosing' => $selfClosing,
            'end' => $i + 1,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseAttributes(string $raw): array
    {
        $pattern = '/(?<name>:?[A-Za-z_][A-Za-z0-9_.-]*)(?:\s*=\s*(?<value>"[^"]*"|\'[^\']*\'|[^\s>]+))?/';

        preg_match_all($pattern, $raw, $matches, PREG_SET_ORDER);

        $attributes = [];

        foreach ($matches as $match) {
            $name = $match['name'];

            // A leading ':' marks a "bound" attribute. Blade would evaluate it
            // as a PHP expression; we never do that — we unwrap any quotes and
            // cast the literal so `:open="true"` / `:count="3"` behave sensibly.
            $bound = str_starts_with($name, ':');

            if ($bound) {
                $name = substr($name, 1);
            }

            $value = $match['value'] ?? '';

            if ($value === '') {
                // Valueless attribute, e.g. `<x-alert dismissible>`.
                $attributes[$name] = true;

                continue;
            }

            $attributes[$name] = $bound
                ? ValueCaster::cast(ValueCaster::unquote($value))
                : ValueCaster::cast($value);
        }

        return $attributes;
    }

    /**
     * Find the start index of the `</x-name>` that closes the tag opened at
     * $from, tracking nested same-name components. Returns null when unbalanced.
     */
    private function findClosingTag(string $text, string $name, int $from): ?int
    {
        $open = '<' . self::PREFIX . $name;
        $close = $this->closeTag($name);
        $length = strlen($text);
        $depth = 1;
        $i = $from;

        while ($i < $length) {
            $nextClose = strpos($text, $close, $i);

            if ($nextClose === false) {
                return null;
            }

            $nextOpen = strpos($text, $open, $i);

            if ($nextOpen !== false && $nextOpen < $nextClose) {
                $nested = $this->parseOpeningTag($text, $nextOpen);

                if ($nested !== null && $this->isBoundary($text, $nextOpen + strlen($open))) {
                    if (! $nested['selfClosing']) {
                        $depth++;
                    }

                    $i = $nested['end'];
                } else {
                    $i = $nextOpen + strlen($open);
                }

                continue;
            }

            $depth--;

            if ($depth === 0) {
                return $nextClose;
            }

            $i = $nextClose + strlen($close);
        }

        return null;
    }

    private function closeTag(string $name): string
    {
        return '</' . self::PREFIX . $name . '>';
    }

    /**
     * A tag name ends only at whitespace, a slash, or the closing bracket — so
     * `<x-tip>` never matches the prefix of `<x-tips>`.
     */
    private function isBoundary(string $text, int $index): bool
    {
        if ($index >= strlen($text)) {
            return true;
        }

        $char = $text[$index];

        return ctype_space($char) || $char === '/' || $char === '>';
    }
}

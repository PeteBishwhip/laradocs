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
    /**
     * @readonly
     * @var \Laradocs\Macros\MacroRegistry
     */
    private $macros;
    private const PREFIX = 'x-';

    public function __construct(MacroRegistry $macros)
    {
        $this->macros = $macros;
    }

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
            [$appended, $offset] = $this->expandTagAt($text, $lt);
            $result .= $appended;
        }

        return $result . substr($text, $offset);
    }

    /**
     * Resolve whatever lives at the `<x-` needle at $lt — a backslash-escape,
     * a malformed tag, an unmatched open, an unknown component, or a render —
     * and return [rendered text to append, offset to resume scanning from].
     *
     * @return array{0: string, 1: int}
     */
    private function expandTagAt(string $text, int $lt): array
    {
        $needle = '<' . self::PREFIX;

        // Backslash-escaped — leave the tag (and its backslash) in place so
        // CommonMark renders the literal angle bracket as text.
        if ($lt > 0 && $text[$lt - 1] === '\\') {
            return [$needle, $lt + strlen($needle)];
        }

        $tag = $this->parseOpeningTag($text, $lt);

        if ($tag === null) {
            return ['<', $lt + 1];
        }

        return $this->renderTag($text, $lt, $tag);
    }

    /**
     * Resolve the slot (for paired tags) and either render the whitelisted
     * component or pass the original source through untouched.
     *
     * @param  array{name: string, attributes: array<string, mixed>, selfClosing: bool, end: int}  $tag
     * @return array{0: string, 1: int}
     */
    private function renderTag(string $text, int $lt, array $tag): array
    {
        $end = $tag['end'];
        $slot = null;

        if (! $tag['selfClosing']) {
            $closeStart = $this->findClosingTag($text, $tag['name'], $tag['end']);

            if ($closeStart === null) {
                // No matching close — treat the opening tag as literal text.
                return [(string) substr($text, $lt, $tag['end'] - $lt), $tag['end']];
            }

            $slot = (string) substr($text, $tag['end'], $closeStart - $tag['end']);
            $end = $closeStart + strlen($this->closeTag($tag['name']));
        }

        if (! $this->macros->has($tag['name'])) {
            // Not whitelisted — pass the original source through untouched.
            return [(string) substr($text, $lt, $end - $lt), $end];
        }

        $arguments = $tag['attributes'];

        if ($slot !== null) {
            // Expand the slot first so nested components render too.
            $arguments['slot'] = trim($this->expand($slot));
        }

        return [$this->macros->render($tag['name'], $arguments), $end];
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

        $name = (string) substr($text, $nameStart, $i - $nameStart);

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

        $rawAttributes = rtrim((string) substr($text, $attrStart, $i - $attrStart));
        $selfClosing = false;

        if (substr_compare($rawAttributes, '/', -strlen('/')) === 0) {
            $selfClosing = true;
            $rawAttributes = (string) substr($rawAttributes, 0, -1);
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
        $attributes = [];
        $length = strlen($raw);
        $offset = 0;

        while ($offset < $length) {
            $offset = $this->skipSpaces($raw, $offset);

            if ($offset >= $length) {
                break;
            }

            $name = $this->matchAttributeName($raw, $offset);

            if ($name === null) {
                $offset++;

                continue;
            }

            $offset += strlen($name);

            // A leading ':' marks a "bound" attribute. Blade would evaluate it
            // as a PHP expression; we never do that — we unwrap any quotes and
            // cast the literal so `:open="true"` / `:count="3"` behave sensibly.
            $bound = strncmp($name, ':', strlen(':')) === 0;

            if ($bound) {
                $name = (string) substr($name, 1);
            }

            [$rawValue, $offset] = $this->readAttributeValue($raw, $offset);

            if ($rawValue === null) {
                // Valueless attribute, e.g. `<x-alert dismissible>`.
                $attributes[$name] = true;

                continue;
            }

            $attributes[$name] = $bound
                ? ValueCaster::cast(ValueCaster::unquote($rawValue))
                : ValueCaster::cast($rawValue);
        }

        return $attributes;
    }

    private function matchAttributeName(string $raw, int $offset): ?string
    {
        if (preg_match('/\G:?[A-Za-z_][\w.-]*/', $raw, $match, 0, $offset) !== 1) {
            return null;
        }

        return $match[0];
    }

    /**
     * Read an optional `= value` following an attribute name. Returns the raw
     * value (quotes preserved so the bound/unbound caller can decide whether to
     * strip them) and the offset after the value — or [null, $offset] when no
     * `=` follows, meaning the attribute is valueless.
     *
     * @return array{0: string|null, 1: int}
     */
    private function readAttributeValue(string $raw, int $offset): array
    {
        $length = strlen($raw);
        $equals = $this->skipSpaces($raw, $offset);

        if ($equals >= $length || $raw[$equals] !== '=') {
            return [null, $offset];
        }

        $i = $this->skipSpaces($raw, $equals + 1);

        // A trailing `=` with nothing after it falls back to a valueless attribute.
        return $i >= $length
            ? [null, $offset]
            : $this->extractValue($raw, $i);
    }

    /**
     * Extract a quoted or bare value starting at $i and return it (quotes
     * preserved) alongside the offset just past it.
     *
     * @return array{0: string, 1: int}
     */
    private function extractValue(string $raw, int $i): array
    {
        $length = strlen($raw);
        $first = $raw[$i];

        if ($first === '"' || $first === "'") {
            // parseOpeningTag only ends a tag outside quotes, so a value-opening
            // quote is always balanced within $raw; the false branch is a guard.
            $closing = strpos($raw, $first, $i + 1);
            $stop = $closing === false ? $length : $closing + 1;

            return [(string) substr($raw, $i, $stop - $i), $stop];
        }

        $end = $i;

        while ($end < $length && ! ctype_space($raw[$end]) && $raw[$end] !== '>') {
            $end++;
        }

        return [(string) substr($raw, $i, $end - $i), $end];
    }

    private function skipSpaces(string $text, int $offset): int
    {
        $length = strlen($text);

        while ($offset < $length && ctype_space($text[$offset])) {
            $offset++;
        }

        return $offset;
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
                [$depth, $i] = $this->advancePastNestedOpen($text, $open, $nextOpen, $depth);

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

    /**
     * Advance past a same-name opening tag encountered while scanning for the
     * matching close. Bumps depth for genuine nested opens, skips over the
     * prefix when the match is a prefix-collision (`<x-foo` inside `<x-foobar`).
     *
     * @return array{0: int, 1: int}
     */
    private function advancePastNestedOpen(string $text, string $open, int $nextOpen, int $depth): array
    {
        $nested = $this->parseOpeningTag($text, $nextOpen);
        $isGenuine = $nested !== null && $this->isBoundary($text, $nextOpen + strlen($open));

        if (! $isGenuine) {
            return [$depth, $nextOpen + strlen($open)];
        }

        if (! $nested['selfClosing']) {
            $depth++;
        }

        return [$depth, $nested['end']];
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

<?php

declare(strict_types=1);

namespace Laradocs\Seo;

use Illuminate\Support\Str;

/**
 * Derive a clean, plain-text excerpt from a markdown document — used to
 * auto-generate a meta description when a page declares none.
 */
final class Excerpt
{
    /**
     * Build a single-paragraph excerpt of at most $limit characters from the
     * given markdown body. Returns null when no prose can be extracted.
     */
    public static function fromMarkdown(string $markdown, int $limit = 160): ?string
    {
        $text = self::firstParagraph($markdown);

        if ($text === '') {
            return null;
        }

        $text = self::stripInline($text);
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        if ($text === '') {
            return null;
        }

        return Str::limit($text, $limit);
    }

    /**
     * Pull the first prose paragraph out of a markdown body, skipping
     * front-matter remnants, headings, fenced code, HTML comments, blockquote
     * and callout markers, and list/table scaffolding.
     */
    private static function firstParagraph(string $markdown): string
    {
        // Drop fenced code blocks wholesale so we never quote raw code.
        $markdown = preg_replace('/^```.*?^```/ms', '', $markdown) ?? $markdown;
        // Drop HTML comments.
        $markdown = preg_replace('/<!--.*?-->/s', '', $markdown) ?? $markdown;

        $paragraph = [];

        foreach (preg_split('/\R/', $markdown) ?: [] as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                // A blank line ends the paragraph once we've collected prose.
                if ($paragraph !== []) {
                    break;
                }

                continue;
            }

            if (self::isSkippable($trimmed)) {
                continue;
            }

            $paragraph[] = $trimmed;
        }

        return implode(' ', $paragraph);
    }

    /**
     * Lines that should never seed an excerpt: headings, list/table/quote
     * markers, horizontal rules and leftover front-matter fences.
     */
    private static function isSkippable(string $line): bool
    {
        return (bool) preg_match('/^(#{1,6}\s|>|[-*+]\s|\d+\.\s|\||---|===|:::|\[!)/', $line);
    }

    /**
     * Reduce inline markdown to its readable text: unwrap links, drop images,
     * strip emphasis / code / heading markers and any stray HTML tags.
     */
    private static function stripInline(string $text): string
    {
        $replacements = [
            '/!\[[^\]]*\]\([^)]*\)/' => '',          // images
            '/\[([^\]]+)\]\([^)]*\)/' => '$1',       // links -> label
            '/`([^`]*)`/' => '$1',                    // inline code
            '/[*_~]+/' => '',                          // emphasis / strikethrough
            '/<[^>]+>/' => '',                         // stray HTML tags
        ];

        $text = preg_replace(array_keys($replacements), array_values($replacements), $text) ?? $text;

        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
    }
}

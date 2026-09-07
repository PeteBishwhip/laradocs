<?php

declare(strict_types=1);

namespace Laradocs\Ai;

use Laradocs\Support\Config;
use Laradocs\Support\Locale;

/**
 * Builds the assistant's system prompt.
 *
 * The default asks for one thing above all others: that the answer come from
 * the documentation and nowhere else. A documentation assistant that fills
 * gaps from its training is worse than no assistant, because a confident
 * answer about a setting that does not exist costs the reader more time than
 * finding nothing would have.
 *
 * A deployment that wants a different prompt sets `laradocs.ai.instructions`
 * and gets it verbatim. A deployment that wants the default plus a little more
 * registers `Laradocs::chatContext()` callbacks, whose lines are appended
 * below whichever prompt is in force.
 */
final class Instructions
{
    /**
     * @param  list<string>  $context  Extra lines from registered resolvers.
     */
    public static function build(ChatRequest $request, array $context = []): string
    {
        $configured = Config::nullableString('laradocs.ai.instructions');

        $sections = [$configured ?? self::default($request)];

        if ($context !== []) {
            $sections[] = "Additional context for this reader:\n" . self::bullets($context);
        }

        return implode("\n\n", $sections);
    }

    private static function default(ChatRequest $request): string
    {
        $title = Config::string('laradocs.ui.brand.title', 'Documentation');
        $locale = $request->locale ?? Locale::fallback();

        $rules = [
            'Search before you answer. Call search_docs with the reader\'s own wording, then fetch_page for any page you intend to rely on, and use only what those pages actually say.',
            'If the documentation does not answer the question, say so plainly and point at the closest page you did find. Never invent a page, a setting, an option, a command or a URL.',
            'Cite every page you drew on as a markdown link, using the url each tool returns for it.',
            'Lead with the answer, then the detail. Two or three short paragraphs is usually plenty.',
            'Put code, configuration and commands in fenced code blocks with a language.',
            'Your tools return only the documentation this reader is allowed to read. Treat anything they do not return as non-existent, and never speculate about pages you cannot see.',
            'Answer in the language identified by the tag "' . $locale . '", whatever language the question was asked in.',
        ];

        if ($request->version !== null && $request->version !== '') {
            $rules[] = 'The reader is on version "' . $request->version . '" of the documentation. Answer for that version.';
        }

        return 'You are the documentation assistant for ' . $title . '. You answer questions about this '
            . "product using its documentation, which you read through your tools.\n\n"
            . self::bullets($rules);
    }

    /**
     * @param  list<string>  $lines
     */
    private static function bullets(array $lines): string
    {
        return implode("\n", array_map(
            static fn (string $line): string => '- ' . $line,
            $lines,
        ));
    }
}

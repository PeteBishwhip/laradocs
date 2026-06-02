<?php

declare(strict_types=1);

namespace Laradocs\Extensions;

use Laradocs\Contracts\MarkdownExtension;
use Laradocs\Support\CodeAwareReplacer;
use Laradocs\Variables\VariableRegistry;

/**
 * Replaces {{ key }} / {{ a.b }} tokens with values from the registry.
 */
final class VariableExtension implements MarkdownExtension
{
    public function __construct(
        private readonly VariableRegistry $variables,
        private readonly string $onUnknown = 'blank',
    ) {}

    public function processMarkdown(string $markdown): string
    {
        return CodeAwareReplacer::apply($markdown, function (string $text): string {
            return preg_replace_callback(
                '/\{\{\s*([a-zA-Z0-9_.\-]+)\s*\}\}/',
                function (array $matches): string {
                    $key = $matches[1];

                    if (! $this->variables->has($key)) {
                        return $this->onUnknown === 'raw' ? $matches[0] : '';
                    }

                    $value = $this->variables->get($key);

                    if (! is_scalar($value)) {
                        return '';
                    }

                    // Escape interpolated values: variable data may originate
                    // from a database or closure and must not inject HTML, even
                    // though the parser otherwise allows authored raw HTML.
                    return htmlspecialchars((string) $value, ENT_QUOTES);
                },
                $text
            ) ?? $text;
        });
    }
}

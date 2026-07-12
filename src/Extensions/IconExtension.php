<?php

declare(strict_types=1);

namespace Laradocs\Extensions;

use Laradocs\Contracts\MarkdownExtension;
use Laradocs\Icons\IconReference;
use Laradocs\Icons\IconRegistry;
use Laradocs\Support\CodeAwareReplacer;

/**
 * Expands @icon('name') and @icon('name', variant: 'solid', set: 'heroicons')
 * shorthand into rendered icon HTML.
 *
 * This complement to the @docs('icon', ...) macro approach allows a more
 * natural inline syntax, matching common template engines.
 */
final class IconExtension implements MarkdownExtension
{
    /**
     * @readonly
     * @var \Laradocs\Icons\IconRegistry
     */
    private $icons;
    /**
     * Matches an `@icon(...)` call, capturing the inner argument string.
     * Shared with the linter so both recognise calls the same way.
     */
    public const CALL_PATTERN = '/@icon\(([^)]+)\)/';

    public function __construct(IconRegistry $icons)
    {
        $this->icons = $icons;
    }

    public function processMarkdown(string $markdown): string
    {
        return CodeAwareReplacer::apply($markdown, function (string $text): string {
            return $this->expand($text);
        });
    }

    private function expand(string $text): string
    {
        return (string) preg_replace_callback(
            self::CALL_PATTERN,
            function (array $matches): string {
                $reference = IconReference::parse($matches[1]);

                if ($reference->name === '') {
                    return $matches[0];
                }

                return $this->icons->render($reference->name, $reference->variant, $reference->set);
            },
            $text,
        );
    }
}

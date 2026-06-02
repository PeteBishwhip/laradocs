<?php

declare(strict_types=1);

namespace Laradocs\Parsers;

use Laradocs\Contracts\DocumentParser;
use Laradocs\Contracts\HtmlExtension;
use Laradocs\Contracts\MarkdownExtension;
use League\CommonMark\ConverterInterface;

final class MarkdownParser implements DocumentParser
{
    /**
     * @param  array<int, MarkdownExtension>  $markdownExtensions
     * @param  array<int, HtmlExtension>  $htmlExtensions
     */
    public function __construct(
        private readonly ConverterInterface $converter,
        private readonly array $markdownExtensions = [],
        private readonly array $htmlExtensions = [],
    ) {}

    public function parse(string $markdown): string
    {
        foreach ($this->markdownExtensions as $extension) {
            $markdown = $extension->processMarkdown($markdown);
        }

        $html = $this->converter->convert($markdown)->getContent();

        foreach ($this->htmlExtensions as $extension) {
            $html = $extension->processHtml($html);
        }

        return $html;
    }
}

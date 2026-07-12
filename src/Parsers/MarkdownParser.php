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
     * @readonly
     * @var \League\CommonMark\ConverterInterface
     */
    private $converter;
    /**
     * @var array<int, MarkdownExtension>
     * @readonly
     */
    private $markdownExtensions = [];
    /**
     * @var array<int, HtmlExtension>
     * @readonly
     */
    private $htmlExtensions = [];
    /**
     * @param  array<int, MarkdownExtension>  $markdownExtensions
     * @param  array<int, HtmlExtension>  $htmlExtensions
     */
    public function __construct(ConverterInterface $converter, array $markdownExtensions = [], array $htmlExtensions = [])
    {
        $this->converter = $converter;
        $this->markdownExtensions = $markdownExtensions;
        $this->htmlExtensions = $htmlExtensions;
    }

    public function parse(string $markdown): string
    {
        foreach ($this->markdownExtensions as $extension) {
            $markdown = $extension->processMarkdown($markdown);
        }

        $html = $this->converter->convertToHtml($markdown);

        foreach ($this->htmlExtensions as $extension) {
            $html = $extension->processHtml($html);
        }

        return $html;
    }
}

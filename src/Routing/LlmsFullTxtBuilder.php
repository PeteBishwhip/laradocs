<?php

declare(strict_types=1);

namespace Laradocs\Routing;

use Laradocs\Contracts\MarkdownExtension;
use Laradocs\Documents\Document;
use Laradocs\Documents\DocumentTree;
use Laradocs\Documents\TreeNode;
use Laradocs\Support\Config;

/**
 * Renders `llms-full.txt`: the entire documentation corpus in one plain-text
 * response, so a model can load the whole site without fetching a page at a
 * time. Same tree, same traversal and the same {@see IndexInclusion} rules as
 * {@see LlmsTxtBuilder}, whose header this class reuses rather than
 * reimplementing — the index and the corpus can never describe different sets
 * of pages.
 *
 * Each entry carries a page's content instead of a link to it. Content is the
 * document's markdown passed through the interpolation-only extensions
 * supplied to the constructor — variables, macros and Blade-component tags,
 * by convention — but never the HTML renderer, so no markup reaches what is
 * meant to be a plain-text file. Extensions that emit HTML from
 * `processMarkdown()` (icon shorthand, inline version blocks) are
 * deliberately not run here: their raw authoring syntax reaches the corpus
 * unexpanded rather than injecting markup into it. A page backed by an
 * OpenAPI spec carries no markdown of its own — its body is produced at
 * render time by `OpenApiContentRenderer` — so it is reduced to a
 * link-and-description stub instead of an empty entry.
 */
final class LlmsFullTxtBuilder
{
    /**
     * @param  array<int, MarkdownExtension>  $extensions
     */
    public function __construct(
        private readonly array $extensions = [],
    ) {}

    public function build(DocumentTree $tree): string
    {
        $body = (new LlmsTxtBuilder)->header();
        $limit = Config::int('laradocs.llms.full_max_bytes', 0);
        $truncated = false;

        foreach ($this->documents($tree) as $document) {
            $entry = $this->entry($document);

            if ($limit > 0 && strlen($body) + strlen($entry) > $limit) {
                $truncated = true;

                break;
            }

            $body .= $entry;
        }

        if ($truncated) {
            $body .= $this->truncationNotice($limit);
        }

        return $body;
    }

    /**
     * The root document (when included) followed by every included document
     * in tree order, exactly as {@see LlmsTxtBuilder} lists them, only
     * flattened: llms-full.txt attributes each entry to its own page heading
     * rather than grouping pages under a section H2.
     *
     * @return array<int, Document>
     */
    private function documents(DocumentTree $tree): array
    {
        $documents = [];

        if ($tree->rootDocument !== null && IndexInclusion::allows($tree->rootDocument)) {
            $documents[] = $tree->rootDocument;
        }

        $this->collect($tree->navigation(), $documents);

        return $documents;
    }

    /**
     * @param  array<int, TreeNode>  $nodes
     * @param  array<int, Document>  $documents
     */
    private function collect(array $nodes, array &$documents): void
    {
        foreach ($nodes as $node) {
            if ($node->document !== null && IndexInclusion::allows($node->document)) {
                $documents[] = $node->document;
            }

            $this->collect($node->children, $documents);
        }
    }

    /**
     * One page: an H2 naming it and linking its canonical URL (so a model can
     * attribute a passage back to the page it came from), then its content.
     */
    private function entry(Document $document): string
    {
        $heading = '[' . $this->label($document->title()) . '](' . DocumentUrl::toSlug($document->slug) . ')';

        return "\n## {$heading}\n\n" . $this->content($document) . "\n";
    }

    private function content(Document $document): string
    {
        if ($this->isOpenApi($document)) {
            return $this->openApiStub($document);
        }

        $markdown = $document->markdown;

        foreach ($this->extensions as $extension) {
            $markdown = $extension->processMarkdown($markdown);
        }

        return trim($markdown) . "\n";
    }

    /**
     * Whether this document is a synthetic OpenAPI page: its `openapi`
     * front-matter marker (set by the OpenAPI loader) carries no markdown body
     * to interpolate, since `OpenApiContentRenderer` produces its content at
     * render time from the parsed spec instead.
     */
    private function isOpenApi(Document $document): bool
    {
        $marker = $document->metadata->get('openapi');

        return is_array($marker) && $marker !== [];
    }

    private function openApiStub(Document $document): string
    {
        $description = $document->metadata->description;

        if ($description === null || trim($description) === '') {
            return "See the linked page for the full API reference.\n";
        }

        return $this->oneLine($description) . "\n";
    }

    private function truncationNotice(int $limit): string
    {
        return "\n---\n\n**Truncated** at " . number_format($limit)
            . " bytes (`llms.full_max_bytes`). See `llms.txt` for the complete page index.\n";
    }

    /**
     * Square brackets would terminate a markdown link label early, so escape
     * them; a stray newline in a title would break the heading, so flatten it.
     */
    private function label(string $title): string
    {
        return str_replace(['[', ']'], ['\[', '\]'], $this->oneLine($title));
    }

    private function oneLine(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}

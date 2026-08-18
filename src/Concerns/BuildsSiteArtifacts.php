<?php

declare(strict_types=1);

namespace Laradocs\Concerns;

use Laradocs\Contracts\MarkdownExtension;
use Laradocs\Extensions\BladeComponentExtension;
use Laradocs\Extensions\MacroExtension;
use Laradocs\Extensions\VariableExtension;
use Laradocs\Routing\FeedBuilder;
use Laradocs\Routing\LlmsFullTxtBuilder;
use Laradocs\Routing\LlmsTxtBuilder;
use Laradocs\Routing\SitemapBuilder;
use Laradocs\Support\Config;

/**
 * The whole-site text artifacts served straight from a route and cached as
 * strings: `sitemap.xml`, `llms.txt`, `llms-full.txt` and the feed.
 *
 * These belong together rather than scattered through the service's document and
 * configuration API: each renders the same document set to a different format,
 * is keyed by the same collection signature, and busts the moment any page
 * changes.
 */
trait BuildsSiteArtifacts
{
    /**
     * The rendered, cached sitemap XML listing every visible, non-redirected
     * page in tree order. Busts automatically when any document changes.
     */
    public function sitemap(): string
    {
        $documents = $this->all();

        return $this->cache->rememberSitemap(
            $documents,
            fn (): string => (new SitemapBuilder)->build($this->tree())
        );
    }

    /**
     * The rendered, cached llms.txt index: an llmstxt.org-style plain-text map
     * of every visible, non-redirected page, in tree order. Busts automatically
     * when any document changes.
     */
    public function llmsTxt(): string
    {
        $documents = $this->all();

        return $this->cache->rememberLlmsTxt(
            $documents,
            fn (): string => (new LlmsTxtBuilder)->build($this->tree())
        );
    }

    /**
     * The rendered, cached llms-full.txt corpus: the same pages as
     * {@see llmsTxt()}, each carrying its content instead of a link. Busts
     * automatically when any document changes.
     */
    public function llmsFullTxt(): string
    {
        $documents = $this->all();

        return $this->cache->rememberLlmsFullTxt(
            $documents,
            fn (): string => (new LlmsFullTxtBuilder($this->llmsFullTxtExtensions()))->build($this->tree())
        );
    }

    /**
     * The interpolation-only markdown extensions llms-full.txt runs over each
     * page: variables, macros and Blade-component tags, gated by the same
     * `parser.extensions` flags the HTML pipeline honours. Icon shorthand and
     * inline version blocks are deliberately excluded — both emit HTML from
     * `processMarkdown()`, and this corpus is plain text.
     *
     * @return array<int, MarkdownExtension>
     */
    private function llmsFullTxtExtensions(): array
    {
        $config = Config::array('laradocs.parser.extensions');
        $extensions = [];

        if ($config['variables'] ?? true) {
            $extensions[] = new VariableExtension(
                $this->variables,
                Config::string('laradocs.parser.unknown_variable', 'blank'),
            );
        }

        if ($config['macros'] ?? true) {
            $extensions[] = new MacroExtension($this->macros);
        }

        if ($config['components'] ?? true) {
            $extensions[] = new BladeComponentExtension($this->macros);
        }

        return $extensions;
    }

    /**
     * The rendered, cached feed XML (RSS 2.0 or Atom 1.0) listing the N
     * most-recently-updated visible, non-redirected pages. Busts automatically
     * when any document changes.
     */
    public function feed(string $format, int $limit, string $feedUrl, string $siteTitle): string
    {
        $documents = $this->all();

        return $this->cache->rememberFeed(
            $documents,
            $format,
            fn (): string => (new FeedBuilder)->build($documents, $format, $limit, $feedUrl, $siteTitle)
        );
    }
}

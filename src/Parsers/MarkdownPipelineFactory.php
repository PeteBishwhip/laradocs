<?php

declare(strict_types=1);

namespace Laradocs\Parsers;

use Illuminate\Contracts\Foundation\Application;
use Laradocs\Contracts\HtmlExtension;
use Laradocs\Contracts\MarkdownExtension;
use Laradocs\Extensions\BladeComponentExtension;
use Laradocs\Extensions\CalloutExtension;
use Laradocs\Extensions\CodeBlockExtension;
use Laradocs\Extensions\HeadingAnchorExtension;
use Laradocs\Extensions\IconExtension;
use Laradocs\Extensions\ImageExtension;
use Laradocs\Extensions\KatexExtension;
use Laradocs\Extensions\MacroExtension;
use Laradocs\Extensions\MermaidExtension;
use Laradocs\Extensions\TabsHtmlExtension;
use Laradocs\Extensions\TabsMarkdownExtension;
use Laradocs\Extensions\VariableExtension;
use Laradocs\Extensions\VersionBlockExtension;
use Laradocs\Extensions\VideoExtension;
use Laradocs\Icons\IconRegistry;
use Laradocs\Macros\MacroRegistry;
use Laradocs\Support\Config;
use Laradocs\Variables\VariableRegistry;
use League\CommonMark\Environment;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\CommonMarkCoreExtension;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

final class MarkdownPipelineFactory
{
    public static function buildConverter(): MarkdownConverter
    {
        $extensions = Config::array('laradocs.parser.extensions');

        $environment = new Environment;
        $environment->mergeConfig([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);

        if ($extensions['gfm'] ?? true) {
            $environment->addExtension(new GithubFlavoredMarkdownExtension);
        }

        if ($extensions['attributes'] ?? true) {
            $environment->addExtension(new AttributesExtension);
        }

        if ($extensions['footnotes'] ?? true) {
            $environment->addExtension(new FootnoteExtension);
        }

        return new MarkdownConverter($environment);
    }

    /**
     * @return array<int, MarkdownExtension>
     */
    public static function markdownExtensions(Application $app): array
    {
        $config = Config::array('laradocs.parser.extensions');
        $extensions = [];

        // Must run first so the `:::version-*` directives are rewritten into
        // HTML blocks before the variable/macro/component extensions process
        // their inner content.
        if (Config::bool('laradocs.versions.inline.enabled', false)) {
            $extensions[] = new VersionBlockExtension(
                Config::string('laradocs.versions.inline.behaviour', 'client') === 'server',
            );
        }

        if ($config['variables'] ?? true) {
            $extensions[] = new VariableExtension(
                $app->make(VariableRegistry::class),
                Config::string('laradocs.parser.unknown_variable', 'blank'),
            );
        }

        if ($config['icons'] ?? true) {
            $extensions[] = new IconExtension($app->make(IconRegistry::class));
        }

        if ($config['macros'] ?? true) {
            $extensions[] = new MacroExtension($app->make(MacroRegistry::class));
        }

        // Runs after macros so `@docs()` calls and `{{ variables }}` nested in a
        // component's slot are expanded before the slot is captured.
        if ($config['components'] ?? true) {
            $extensions[] = new BladeComponentExtension($app->make(MacroRegistry::class));
        }

        if ($config['katex'] ?? true) {
            $extensions[] = new KatexExtension(
                Config::string('laradocs.parser.katex.js', 'https://cdn.jsdelivr.net/npm/katex@0.16/dist/katex.min.js'),
                Config::string('laradocs.parser.katex.css', 'https://cdn.jsdelivr.net/npm/katex@0.16/dist/katex.min.css'),
                Config::bool('laradocs.parser.katex.ssr'),
                Config::nullableString('laradocs.parser.katex.node_bin'),
            );
        }

        if ($config['tabs'] ?? true) {
            $extensions[] = new TabsMarkdownExtension;
        }

        return $extensions;
    }

    /**
     * @return array<int, HtmlExtension>
     */
    public static function htmlExtensions(): array
    {
        $config = Config::array('laradocs.parser.extensions');
        $extensions = [];

        if ($config['heading_anchors'] ?? true) {
            $extensions[] = new HeadingAnchorExtension;
        }

        if ($config['callouts'] ?? true) {
            $extensions[] = new CalloutExtension;
        }

        if ($config['video'] ?? true) {
            $extensions[] = new VideoExtension;
        }

        if ($config['images'] ?? true) {
            $extensions[] = new ImageExtension;
        }

        // Runs before CodeBlockExtension so mermaid fences are claimed before
        // they would otherwise pick up a language label and copy button.
        if ($config['mermaid'] ?? true) {
            $extensions[] = new MermaidExtension(
                Config::string(
                    'laradocs.parser.mermaid.src',
                    'https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.esm.min.mjs',
                ),
            );
        }

        if ($config['katex'] ?? true) {
            $extensions[] = new KatexExtension(
                Config::string('laradocs.parser.katex.js', 'https://cdn.jsdelivr.net/npm/katex@0.16/dist/katex.min.js'),
                Config::string('laradocs.parser.katex.css', 'https://cdn.jsdelivr.net/npm/katex@0.16/dist/katex.min.css'),
                Config::bool('laradocs.parser.katex.ssr'),
                Config::nullableString('laradocs.parser.katex.node_bin'),
            );
        }

        $extensions[] = new CodeBlockExtension;

        // TabsHtmlExtension must run after CodeBlockExtension so it can
        // find .laradocs-code[data-tab] wrappers when grouping code tabs.
        if ($config['tabs'] ?? true) {
            $extensions[] = new TabsHtmlExtension(
                Config::string('laradocs.tabs.default_group', 'language'),
            );
        }

        return $extensions;
    }
}

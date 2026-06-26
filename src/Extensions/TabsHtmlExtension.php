<?php

declare(strict_types=1);

namespace Laradocs\Extensions;

use DOMDocument;
use DOMElement;
use DOMText;
use Laradocs\Contracts\HtmlExtension;
use Laradocs\Support\Html;

/**
 * Groups consecutive `.laradocs-code[data-tab]` divs (from code-tab shorthand)
 * and transforms `.laradocs-tab-group-raw` wrapper divs (from content tabs)
 * into fully accessible `<div class="laradocs-tab-group">` tab blocks.
 *
 * This extension must run AFTER CodeBlockExtension so the `.laradocs-code`
 * wrappers with `data-tab` are already in the DOM when grouping starts.
 */
final class TabsHtmlExtension implements HtmlExtension
{
    private int $counter = 0;

    public function __construct(
        private readonly string $defaultGroup = 'language',
    ) {}

    public function processHtml(string $html): string
    {
        return Html::mutate($html, function (DOMDocument $dom, DOMElement $body): void {
            // Pass 1 — content tabs: replace .laradocs-tab-group-raw divs with
            // fully rendered tab-group markup. Inner content is already processed
            // by CommonMark + all earlier HTML extensions.
            foreach (iterator_to_array($body->getElementsByTagName('div')) as $div) {
                if ($div->getAttribute('class') === 'laradocs-tab-group-raw') {
                    $this->processContentTabGroup($dom, $div);
                }
            }

            // Pass 2 — code tabs: collect unique parent elements that contain
            // .laradocs-code-tab-pending[data-tab] children and group consecutive
            // siblings into tab blocks.
            $parents = new \SplObjectStorage;
            foreach (iterator_to_array($body->getElementsByTagName('div')) as $div) {
                if ($this->isCodeTabPending($div) && $div->parentNode instanceof DOMElement) {
                    $parents->attach($div->parentNode);
                }
            }
            foreach ($parents as $parent) {
                $this->groupCodeTabsIn($dom, $parent);
            }
        });
    }

    // ── Content tabs ─────────────────────────────────────────────────────────

    private function processContentTabGroup(DOMDocument $dom, DOMElement $rawGroup): void
    {
        $group = $rawGroup->getAttribute('data-group') ?: 'content';

        $sections = [];
        foreach (iterator_to_array($rawGroup->childNodes) as $child) {
            if ($child instanceof DOMElement && str_contains($child->getAttribute('class'), 'laradocs-tab-raw')) {
                $sections[] = [
                    'label' => $child->getAttribute('data-tab'),
                    'html'  => Html::innerHtml($child),
                ];
            }
        }

        if (empty($sections)) {
            Html::replaceWithHtml($rawGroup, Html::innerHtml($rawGroup));

            return;
        }

        if (count($sections) === 1) {
            $label = htmlspecialchars($sections[0]['label'], ENT_QUOTES, 'UTF-8');
            $inner = $sections[0]['html'];
            $singleHtml = '<div class="laradocs-tab-group laradocs-tab-group--single">';
            if ($label !== '') {
                $singleHtml .= '<div class="laradocs-tab-group-label">' . $label . '</div>';
            }
            $singleHtml .= $inner . '</div>';
            Html::replaceWithHtml($rawGroup, $singleHtml);

            return;
        }

        Html::replaceWithHtml($rawGroup, $this->buildTabGroupHtml($sections, $group));
    }

    // ── Code tabs ────────────────────────────────────────────────────────────

    private function groupCodeTabsIn(DOMDocument $dom, DOMElement $parent): void
    {
        $children = iterator_to_array($parent->childNodes);
        $runs     = [];

        $runNodes = [];
        $runDivs  = [];
        $inRun    = false;

        foreach ($children as $child) {
            if ($child instanceof DOMElement && $this->isCodeTabPending($child)) {
                $runNodes[] = $child;
                $runDivs[]  = $child;
                $inRun      = true;
            } elseif ($inRun && $child instanceof DOMText && trim($child->textContent) === '') {
                // Whitespace between consecutive code-tab blocks — keep in run.
                $runNodes[] = $child;
            } else {
                if (count($runDivs) >= 2) {
                    $runs[] = ['nodes' => $runNodes, 'divs' => $runDivs];
                }
                $runNodes = [];
                $runDivs  = [];
                $inRun    = false;
            }
        }

        if (count($runDivs) >= 2) {
            $runs[] = ['nodes' => $runNodes, 'divs' => $runDivs];
        }

        // Process in reverse order to preserve correct DOM positions.
        foreach (array_reverse($runs) as $run) {
            $this->wrapCodeTabRun($dom, $parent, $run['nodes'], $run['divs']);
        }
    }

    /**
     * @param  array<int, DOMElement>  $tabDivs
     * @param  array<int, \DOMNode>    $allNodes
     */
    private function wrapCodeTabRun(
        DOMDocument $dom,
        DOMElement $parent,
        array $allNodes,
        array $tabDivs,
    ): void {
        $sections = array_map(
            fn (DOMElement $el): array => [
                'label' => $el->getAttribute('data-tab'),
                'html'  => Html::innerHtml($el),
            ],
            $tabDivs,
        );

        $html = $this->buildTabGroupHtml($sections, $this->defaultGroup);

        $refNode = $allNodes[0];
        foreach (Html::fragment($dom, $html) as $newNode) {
            $parent->insertBefore($newNode, $refNode);
        }

        foreach ($allNodes as $node) {
            if ($node->parentNode === $parent) {
                $parent->removeChild($node);
            }
        }
    }

    private function isCodeTabPending(DOMElement $el): bool
    {
        return str_contains($el->getAttribute('class'), 'laradocs-code-tab-pending')
            && $el->hasAttribute('data-tab');
    }

    // ── Shared HTML builder ───────────────────────────────────────────────────

    /**
     * Build the accessible tab-group markup for both code tabs and content tabs.
     *
     * @param  array<int, array{label: string, html: string}>  $sections
     */
    private function buildTabGroupHtml(array $sections, string $group): string
    {
        $id = 'tg' . (++$this->counter);

        $escapedGroup = htmlspecialchars($group, ENT_QUOTES, 'UTF-8');

        $html  = '<div class="laradocs-tab-group" ';
        $html .= 'data-tabs-group="' . $escapedGroup . '" ';
        $html .= 'data-tabs-id="' . $id . '">';

        // Tab list
        $html .= '<div class="laradocs-tab-group-list" role="tablist">';
        foreach ($sections as $i => $section) {
            $tabId    = $id . '-' . $i;
            $selected = $i === 0 ? 'true' : 'false';
            $cls      = 'laradocs-tab' . ($i === 0 ? ' is-active' : '');
            $tabindex = $i === 0 ? '' : ' tabindex="-1"';
            $label    = htmlspecialchars($section['label'], ENT_QUOTES, 'UTF-8');

            $html .= '<button role="tab"'
                . ' id="' . $tabId . '"'
                . ' aria-controls="' . $tabId . '-panel"'
                . ' aria-selected="' . $selected . '"'
                . ' class="' . $cls . '"'
                . $tabindex . '>'
                . $label
                . '</button>';
        }
        $html .= '</div>';

        // Tab panels
        foreach ($sections as $i => $section) {
            $tabId  = $id . '-' . $i;
            $hidden = $i === 0 ? '' : ' hidden';

            $html .= '<div role="tabpanel"'
                . ' id="' . $tabId . '-panel"'
                . ' aria-labelledby="' . $tabId . '"'
                . ' tabindex="0"'
                . $hidden . '>'
                . $section['html']
                . '</div>';
        }

        $html .= '</div>';

        return $html;
    }
}

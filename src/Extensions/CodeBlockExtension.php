<?php

declare(strict_types=1);

namespace Laradocs\Extensions;

use DOMDocument;
use DOMElement;
use Laradocs\Contracts\HtmlExtension;
use Laradocs\Support\Html;

/**
 * Wraps fenced code blocks with a language label and a copy-to-clipboard button.
 */
final class CodeBlockExtension implements HtmlExtension
{
    public function processHtml(string $html): string
    {
        return Html::mutate($html, function (DOMDocument $dom, DOMElement $body): void {
            /** @var DOMElement $pre */
            foreach (iterator_to_array($body->getElementsByTagName('pre')) as $pre) {
                // Mermaid diagrams own their own chrome — they should not get a
                // language label or copy button.
                if (strpos($pre->getAttribute('class'), 'laradocs-mermaid-source') !== false) {
                    continue;
                }

                $this->wrap($dom, $pre);
            }
        });
    }

    private function wrap(DOMDocument $dom, DOMElement $pre): void
    {
        $parent = $pre->parentNode;

        // @codeCoverageIgnoreStart
        if ($parent === null) {
            return;
        }
        // @codeCoverageIgnoreEnd

        $language = $this->language($pre);

        $wrapper = $dom->createElement('div');
        $wrapper->setAttribute('class', 'laradocs-code');

        if ($language !== null) {
            $wrapper->setAttribute('data-language', $language);
        }

        $header = $dom->createElement('div');
        $header->setAttribute('class', 'laradocs-code-header');

        $label = $dom->createElement('span', $language ?? 'code');
        $label->setAttribute('class', 'laradocs-code-lang');
        $header->appendChild($label);

        $button = $dom->createElement('button', 'Copy');
        $button->setAttribute('type', 'button');
        $button->setAttribute('class', 'laradocs-code-copy');
        $button->setAttribute('aria-label', 'Copy code to clipboard');
        $header->appendChild($button);

        $parent->replaceChild($wrapper, $pre);
        $wrapper->appendChild($header);
        $wrapper->appendChild($pre);
    }

    private function language(DOMElement $pre): ?string
    {
        $code = $pre->getElementsByTagName('code')->item(0);

        if (! $code instanceof DOMElement) {
            return null;
        }

        foreach (explode(' ', $code->getAttribute('class')) as $class) {
            if (strncmp($class, 'language-', strlen('language-')) === 0) {
                return (string) substr($class, strlen('language-'));
            }
        }

        return null;
    }
}

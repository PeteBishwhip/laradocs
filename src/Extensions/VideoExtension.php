<?php

declare(strict_types=1);

namespace Laradocs\Extensions;

use DOMDocument;
use DOMElement;
use Laradocs\Contracts\HtmlExtension;
use Laradocs\Support\Html;

/**
 * Turns image/link references to videos into proper players or embeds:
 * local files become <video>, YouTube/Vimeo links become responsive iframes.
 */
final class VideoExtension implements HtmlExtension
{
    /**
     * @var array<string, string>
     */
    private const MIME = [
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'ogg' => 'video/ogg',
        'mov' => 'video/quicktime',
    ];

    public function processHtml(string $html): string
    {
        return Html::mutate($html, function (DOMDocument $dom, DOMElement $body): void {
            /** @var DOMElement $img */
            foreach (iterator_to_array($body->getElementsByTagName('img')) as $img) {
                $this->convert($img, $img->getAttribute('src'));
            }

            /** @var DOMElement $anchor */
            foreach (iterator_to_array($body->getElementsByTagName('a')) as $anchor) {
                if ($this->embedUrl($anchor->getAttribute('href')) !== null) {
                    $this->convert($anchor, $anchor->getAttribute('href'));
                }
            }
        });
    }

    private function convert(DOMElement $node, string $url): void
    {
        if (($embed = $this->embedUrl($url)) !== null) {
            Html::replaceWithHtml($node, sprintf(
                '<div class="laradocs-embed"><iframe src="%s" loading="lazy" '
                . 'frameborder="0" allow="accelerated-downloads; autoplay; encrypted-media; '
                . 'picture-in-picture" allowfullscreen></iframe></div>',
                htmlspecialchars($embed, ENT_QUOTES)
            ));

            return;
        }

        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: $url, PATHINFO_EXTENSION));

        if (isset(self::MIME[$extension])) {
            Html::replaceWithHtml($node, sprintf(
                '<video class="laradocs-video" controls preload="metadata">'
                . '<source src="%s" type="%s"></video>',
                htmlspecialchars($url, ENT_QUOTES),
                self::MIME[$extension]
            ));
        }
    }

    private function embedUrl(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '') {
            return null;
        }

        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $path = (string) parse_url($url, PHP_URL_PATH);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        if ($host === 'youtu.be' && preg_match('#^/([\w-]+)#', $path, $m) === 1) {
            return 'https://www.youtube-nocookie.com/embed/' . $m[1];
        }

        if (in_array($host, ['youtube.com', 'youtube-nocookie.com'], true)) {
            if (isset($query['v']) && is_string($query['v']) && preg_match('/^[\w-]+$/', $query['v']) === 1) {
                return 'https://www.youtube-nocookie.com/embed/' . $query['v'];
            }

            if (preg_match('#^/embed/([\w-]+)#', $path, $m) === 1) {
                return 'https://www.youtube-nocookie.com/embed/' . $m[1];
            }
        }

        if (in_array($host, ['vimeo.com', 'player.vimeo.com'], true)
            && preg_match('#(\d+)#', $path, $m) === 1) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }

        return null;
    }
}

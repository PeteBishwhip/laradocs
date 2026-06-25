<?php

declare(strict_types=1);

namespace Laradocs\Routing;

use Laradocs\Documents\Document;
use Laradocs\Documents\DocumentCollection;

/**
 * Renders an RSS 2.0 or Atom 1.0 feed from a document collection.
 *
 * Documents are sorted by their effective "updated at" timestamp — the
 * front-matter `updated_at` field when present, otherwise the file mtime.
 * Only visible, non-redirected pages are included.
 */
final class FeedBuilder
{
    /**
     * @param  DocumentCollection<int, Document>  $documents
     */
    public function build(
        DocumentCollection $documents,
        string $format,
        int $limit,
        string $feedUrl,
        string $siteTitle,
    ): string {
        $items = $documents
            ->filter(fn (Document $doc): bool => ! $doc->isHidden() && $doc->redirect() === null)
            ->sortByDesc(fn (Document $doc): int => $this->timestamp($doc))
            ->take($limit)
            ->values();

        if ($format === 'atom') {
            return $this->buildAtom($items, $feedUrl, $siteTitle);
        }

        return $this->buildRss($items, $feedUrl, $siteTitle);
    }

    /**
     * @param  DocumentCollection<int, Document>  $items
     */
    private function buildRss(DocumentCollection $items, string $feedUrl, string $siteTitle): string
    {
        $indexUrl = DocumentUrl::index();
        $title = $this->esc($siteTitle);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        $xml .= '  <channel>' . "\n";
        $xml .= '    <title>' . $title . '</title>' . "\n";
        $xml .= '    <link>' . $this->esc($indexUrl) . '</link>' . "\n";
        $xml .= '    <description>' . $title . '</description>' . "\n";
        $xml .= '    <atom:link href="' . $this->esc($feedUrl) . '" rel="self" type="application/rss+xml"/>' . "\n";

        foreach ($items as $document) {
            $url = $this->esc(DocumentUrl::toSlug($document->slug));
            $xml .= '    <item>' . "\n";
            $xml .= '      <title>' . $this->esc($document->title()) . '</title>' . "\n";
            $xml .= '      <link>' . $url . '</link>' . "\n";
            $xml .= '      <guid isPermaLink="true">' . $url . '</guid>' . "\n";
            $xml .= '      <pubDate>' . date('r', $this->timestamp($document)) . '</pubDate>' . "\n";

            $description = $document->metadata->description;
            if ($description !== null && $description !== '') {
                $xml .= '      <description>' . $this->esc($description) . '</description>' . "\n";
            }

            $author = $document->metadata->author;
            if ($author !== null && $author !== '') {
                $xml .= '      <author>' . $this->esc($author) . '</author>' . "\n";
            }

            $xml .= '    </item>' . "\n";
        }

        $xml .= '  </channel>' . "\n";
        $xml .= '</rss>' . "\n";

        return $xml;
    }

    /**
     * @param  DocumentCollection<int, Document>  $items
     */
    private function buildAtom(DocumentCollection $items, string $feedUrl, string $siteTitle): string
    {
        $indexUrl = DocumentUrl::index();
        $feedUpdated = $items->isNotEmpty()
            ? date('c', $this->timestamp($items->first()))
            : date('c', 0);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<feed xmlns="http://www.w3.org/2005/Atom">' . "\n";
        $xml .= '  <title>' . $this->esc($siteTitle) . '</title>' . "\n";
        $xml .= '  <link href="' . $this->esc($indexUrl) . '" rel="alternate" type="text/html"/>' . "\n";
        $xml .= '  <link href="' . $this->esc($feedUrl) . '" rel="self" type="application/atom+xml"/>' . "\n";
        $xml .= '  <updated>' . $feedUpdated . '</updated>' . "\n";
        $xml .= '  <id>' . $this->esc($indexUrl) . '</id>' . "\n";

        foreach ($items as $document) {
            $url = $this->esc(DocumentUrl::toSlug($document->slug));
            $updated = date('c', $this->timestamp($document));

            $xml .= '  <entry>' . "\n";
            $xml .= '    <title>' . $this->esc($document->title()) . '</title>' . "\n";
            $xml .= '    <link href="' . $url . '"/>' . "\n";
            $xml .= '    <id>' . $url . '</id>' . "\n";
            $xml .= '    <updated>' . $updated . '</updated>' . "\n";

            $description = $document->metadata->description;
            if ($description !== null && $description !== '') {
                $xml .= '    <summary>' . $this->esc($description) . '</summary>' . "\n";
            }

            $author = $document->metadata->author;
            if ($author !== null && $author !== '') {
                $xml .= '    <author><name>' . $this->esc($author) . '</name></author>' . "\n";
            }

            $xml .= '  </entry>' . "\n";
        }

        $xml .= '</feed>' . "\n";

        return $xml;
    }

    private function timestamp(Document $document): int
    {
        $carbon = $document->metadata->updatedAtCarbon();

        if ($carbon !== null) {
            return (int) $carbon->timestamp;
        }

        return $document->modifiedAt;
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}

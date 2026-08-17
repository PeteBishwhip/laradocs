<?php

declare(strict_types=1);

namespace Laradocs\Routing;

use Laradocs\Documents\Document;
use Laradocs\Support\Config;
use Laradocs\Support\Version;

/**
 * Which documents belong in a site-wide index.
 *
 * Shared by {@see SitemapBuilder} and {@see LlmsTxtBuilder} so the two
 * artifacts can never disagree about what the site contains: a page listed in
 * `llms.txt` but missing from `sitemap.xml` (or the reverse) is a bug, not a
 * feature.
 */
final class IndexInclusion
{
    /**
     * Hidden pages and pages that redirect elsewhere are excluded: an index
     * should advertise canonical destinations, not interstitials.
     */
    public static function allows(Document $document): bool
    {
        if (self::versionExcluded()) {
            return false;
        }

        return ! $document->isHidden() && $document->redirect() === null;
    }

    /**
     * Whether the active (non-default) version's pages should be left out.
     * Indexes are built per active version, so this gates the whole tree:
     * non-default versions are excluded unless `seo.sitemap_all_versions` opts
     * every version in, which would otherwise leave every version of the docs
     * advertising itself as the site.
     */
    public static function versionExcluded(): bool
    {
        if (! Config::bool('laradocs.versions.enabled', false)) {
            return false;
        }

        if (Config::bool('laradocs.seo.sitemap_all_versions', false)) {
            return false;
        }

        $current = Version::current();

        return $current !== null && ! Version::isDefault($current);
    }
}

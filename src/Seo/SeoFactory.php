<?php

declare(strict_types=1);

namespace Laradocs\Seo;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Laradocs\Documents\Document;
use Laradocs\Documents\TreeNode;
use Laradocs\Metadata\Metadata;
use Laradocs\Routing\DocumentUrl;
use Laradocs\Support\Config;
use RalphJSmit\Laravel\SEO\Schema\BreadcrumbListSchema;
use RalphJSmit\Laravel\SEO\SchemaCollection;
use RalphJSmit\Laravel\SEO\Support\SEOData;
use Throwable;

/**
 * Translates a documentation page into a {@see SEOData} object for
 * ralphjsmit/laravel-seo to render.
 *
 * Good defaults are derived automatically — the page title gains a brand
 * suffix, a meta description is lifted from the opening paragraph when none is
 * declared, dates come from the file's mtime, and Article + BreadcrumbList
 * JSON-LD is emitted. Every field can be overridden from front-matter, either
 * via the top-level keys or a dedicated `seo:` block.
 */
final class SeoFactory
{
    /**
     * The X card type resolved by the last forDocument() / forPage() call.
     * Exposed via xCard() so the controller can pass it as a separate view
     * variable, letting the layout emit an explicit twitter:card tag first
     * (X/Twitter parsers honour the first occurrence).
     */
    private string $lastXCard = 'summary_large_image';

    /**
     * Return the X card type resolved during the most recent
     * forDocument() or forPage() call.
     */
    public function xCard(): string
    {
        return $this->lastXCard;
    }

    /**
     * Build the SEO payload for a rendered document.
     *
     * @param  array<int, TreeNode>  $breadcrumbs  Navigation trail, current page last.
     */
    public function forDocument(Document $document, array $breadcrumbs = []): SEOData
    {
        $meta = $document->metadata;
        $seo = $this->seoBlock($meta);

        $this->lastXCard = $this->resolveXCard($seo, $meta);

        $title = self::asString($this->pick($seo, $meta, 'title')) ?? $document->title();
        $description = self::asString($this->pick($seo, $meta, 'description'))
            ?? $this->autoDescription($document)
            ?? $this->fallbackDescription();

        return new SEOData(
            title: $this->suffixedTitle($title),
            description: $description,
            author: self::asString($this->pick($seo, $meta, 'author')) ?? $this->stringOrNull('laradocs.seo.author'),
            // An explicit image (front-matter or seo.image) always wins; only
            // when none is declared do we fall back to a generated card.
            image: $this->explicitImage($document) ?? DocumentUrl::ogImage($document->slug),
            // We bake the suffix into the title (above) and disable the SEO
            // package's own suffixing, which would otherwise also drag the
            // brand into og:title / x:title. Social cards instead read
            // the clean title from openGraphTitle below.
            enableTitleSuffix: false,
            published_time: $this->publishedTime($seo, $meta),
            modified_time: $this->timestamp($document->modifiedAt),
            section: self::asString($this->pick($seo, $meta, 'section')) ?? $meta->group,
            tags: $this->resolveTags($seo, $meta),
            twitter_username: $this->stringOrNull('laradocs.seo.x'),
            schema: $this->schema($breadcrumbs),
            type: self::asString($this->pick($seo, $meta, 'type')) ?? $this->stringOrNull('laradocs.seo.type') ?? 'article',
            site_name: $this->siteName(),
            favicon: $this->stringOrNull('laradocs.ui.brand.favicon'),
            robots: $this->resolveRobots($seo, $meta),
            canonical_url: self::asString($this->pick($seo, $meta, 'canonical')),
            openGraphTitle: $title,
        );
    }

    /**
     * Build the SEO payload for a page without a backing document — the home
     * landing page and the empty state.
     */
    public function forPage(?string $title = null, ?string $description = null): SEOData
    {
        $title ??= $this->siteName();

        $this->lastXCard = $this->stringOrNull('laradocs.seo.x_card') ?? 'summary_large_image';

        return new SEOData(
            title: $this->suffixedTitle($title),
            description: $description ?? $this->fallbackDescription(),
            author: $this->stringOrNull('laradocs.seo.author'),
            image: $this->stringOrNull('laradocs.seo.image') ?? DocumentUrl::ogImage(''),
            enableTitleSuffix: false,
            twitter_username: $this->stringOrNull('laradocs.seo.x'),
            type: 'website',
            site_name: $this->siteName(),
            favicon: $this->stringOrNull('laradocs.ui.brand.favicon'),
            // Social cards read the clean, un-suffixed title.
            openGraphTitle: $title,
        );
    }

    /**
     * Resolve the X card type: seo.x_card front-matter block takes priority,
     * then the top-level x_card key, then the site-wide config, and finally
     * the hard-coded default.
     *
     * @param  array<string, mixed>  $seo
     */
    private function resolveXCard(array $seo, Metadata $meta): string
    {
        $value = self::asString($this->pick($seo, $meta, 'x_card'));

        return $value ?? $this->stringOrNull('laradocs.seo.x_card') ?? 'summary_large_image';
    }

    /**
     * Derive a meta description from the page body when one isn't declared and
     * auto-description is enabled.
     */
    private function autoDescription(Document $document): ?string
    {
        if (! Config::bool('laradocs.seo.auto_description', true)) {
            return null;
        }

        return Excerpt::fromMarkdown($document->markdown);
    }

    /**
     * @param  array<string, mixed>  $seo
     */
    private function resolveRobots(array $seo, Metadata $meta): ?string
    {
        $robots = self::asString($this->pick($seo, $meta, 'robots'));

        if ($robots === null && self::truthy($this->pick($seo, $meta, 'noindex'))) {
            $robots = 'noindex, nofollow';
        }

        return $robots ?? $this->stringOrNull('laradocs.seo.robots');
    }

    /**
     * @param  array<string, mixed>  $seo
     * @return array<int, string>|null
     */
    private function resolveTags(array $seo, Metadata $meta): ?array
    {
        $tags = $this->pick($seo, $meta, 'tags');

        if (! is_array($tags) || $tags === []) {
            return null;
        }

        $tags = array_values(array_filter(array_map(
            static fn (mixed $tag): string => is_scalar($tag) ? trim((string) $tag) : '',
            $tags,
        )));

        return $tags === [] ? null : $tags;
    }

    /**
     * @param  array<string, mixed>  $seo
     */
    private function publishedTime(array $seo, Metadata $meta): ?CarbonInterface
    {
        $value = $seo['published_at'] ?? $seo['date']
            ?? $meta->get('published_at') ?? $meta->get('date');

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function timestamp(int $unix): ?CarbonInterface
    {
        return $unix > 0 ? Carbon::createFromTimestamp($unix) : null;
    }

    /**
     * Build the JSON-LD schema collection (Article + BreadcrumbList) according
     * to configuration. Returns null when both are disabled.
     *
     * @param  array<int, TreeNode>  $breadcrumbs
     * @return SchemaCollection<array-key>|null
     */
    private function schema(array $breadcrumbs): ?SchemaCollection
    {
        $wantsArticle = Config::bool('laradocs.seo.schema.article', true);
        $wantsBreadcrumbs = Config::bool('laradocs.seo.schema.breadcrumbs', true);

        if (! $wantsArticle && ! $wantsBreadcrumbs) {
            return null;
        }

        $schema = SchemaCollection::make();

        if ($wantsArticle) {
            $schema = $schema->addArticle();
        }

        if ($wantsBreadcrumbs) {
            $trail = $this->breadcrumbTrail($breadcrumbs);

            $schema = $schema->addBreadcrumbs(
                static fn (BreadcrumbListSchema $b): BreadcrumbListSchema => $b->prependBreadcrumbs($trail),
            );
        }

        return $schema;
    }

    /**
     * Map the navigation trail (minus the current page, which the schema seeds
     * itself) to a label => URL list, prefixed with the docs home.
     *
     * @param  array<int, TreeNode>  $breadcrumbs
     * @return array<string, string>
     */
    private function breadcrumbTrail(array $breadcrumbs): array
    {
        $trail = ['Home' => DocumentUrl::index()];

        foreach (array_slice($breadcrumbs, 0, -1) as $crumb) {
            if ($crumb->isLink()) {
                $trail[$crumb->title] = DocumentUrl::toSlug($crumb->slug);
            }
        }

        return $trail;
    }

    /**
     * The image a page explicitly declares — its front-matter `image` (top-level
     * or in the `seo:` block), then the site-wide `seo.image` default. Returns
     * null when the page leaves the choice to generation.
     *
     * Exposed so the og-image controller can honour the same precedence the
     * meta tags do: an explicit image short-circuits generation entirely.
     */
    public function explicitImage(Document $document): ?string
    {
        $meta = $document->metadata;
        $seo = $this->seoBlock($meta);

        return self::asString($this->pick($seo, $meta, 'image'))
            ?? $this->stringOrNull('laradocs.seo.image');
    }

    /**
     * Resolve a value, preferring the dedicated `seo:` block over the matching
     * top-level front-matter key.
     *
     * @param  array<string, mixed>  $seo
     */
    private function pick(array $seo, Metadata $meta, string $key): mixed
    {
        if (array_key_exists($key, $seo)) {
            return $seo[$key];
        }

        return $meta->get($key);
    }

    /**
     * @return array<string, mixed>
     */
    private function seoBlock(Metadata $meta): array
    {
        $seo = $meta->get('seo');

        if (! is_array($seo)) {
            return [];
        }

        /** @var array<string, mixed> $seo */
        return $seo;
    }

    private function siteName(): string
    {
        return $this->stringOrNull('laradocs.seo.site_name')
            ?? Config::string('laradocs.ui.brand.title', 'Documentation');
    }

    /**
     * Append the configured (or derived) brand suffix to a page title, skipping
     * it when the title already is the site name (e.g. the home page) to avoid
     * "Acme Docs · Acme Docs".
     */
    private function suffixedTitle(string $title): string
    {
        $suffix = Config::nullableString('laradocs.seo.title_suffix');

        if ($suffix === null) {
            $site = $this->siteName();
            $suffix = $site !== '' && ! self::same($title, $site) ? ' · ' . $site : '';
        }

        return $title . $suffix;
    }

    private function fallbackDescription(): ?string
    {
        return $this->stringOrNull('laradocs.seo.description')
            ?? $this->stringOrNull('laradocs.ui.brand.tagline');
    }

    private function stringOrNull(string $key): ?string
    {
        $value = Config::nullableString($key);

        return $value === null || $value === '' ? null : $value;
    }

    private static function asString(mixed $value): ?string
    {
        if (is_string($value)) {
            return trim($value) === '' ? null : $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }

    private static function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    private static function same(string $a, string $b): bool
    {
        return mb_strtolower(trim($a)) === mb_strtolower(trim($b));
    }
}

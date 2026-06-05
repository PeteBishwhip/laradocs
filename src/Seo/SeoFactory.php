<?php

declare(strict_types=1);

namespace Laradocs\Seo;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Laradocs\Documents\Document;
use Laradocs\Documents\TreeNode;
use Laradocs\Metadata\Metadata;
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
     * Build the SEO payload for a rendered document.
     *
     * @param  array<int, TreeNode>  $breadcrumbs  Navigation trail, current page last.
     */
    public function forDocument(Document $document, array $breadcrumbs = []): SEOData
    {
        $meta = $document->metadata;
        $seo = $this->seoBlock($meta);

        $title = self::asString($this->pick($seo, $meta, 'title')) ?? $document->title();
        $description = self::asString($this->pick($seo, $meta, 'description'))
            ?? $this->autoDescription($document);

        return $this->build(
            title: $title,
            description: $description,
            image: self::asString($this->pick($seo, $meta, 'image')) ?? $this->stringOrNull('laradocs.seo.image'),
            author: self::asString($this->pick($seo, $meta, 'author')) ?? $this->stringOrNull('laradocs.seo.author'),
            type: self::asString($this->pick($seo, $meta, 'type')) ?? $this->stringOrNull('laradocs.seo.type') ?? 'article',
            robots: $this->resolveRobots($seo, $meta),
            canonical: self::asString($this->pick($seo, $meta, 'canonical')),
            tags: $this->resolveTags($seo, $meta),
            section: self::asString($this->pick($seo, $meta, 'section')) ?? $meta->group,
            publishedTime: $this->publishedTime($seo, $meta),
            modifiedTime: $this->timestamp($document->modifiedAt),
            schema: $this->schema($breadcrumbs),
        );
    }

    /**
     * Build the SEO payload for a page without a backing document — the home
     * landing page and the empty state.
     */
    public function forPage(?string $title = null, ?string $description = null): SEOData
    {
        return $this->build(
            title: $title ?? $this->siteName(),
            description: $description,
            image: $this->stringOrNull('laradocs.seo.image'),
            author: $this->stringOrNull('laradocs.seo.author'),
            type: 'website',
        );
    }

    /**
     * Assemble a {@see SEOData} from resolved per-page values layered over the
     * site-wide defaults.
     *
     * @param  array<int, string>|null  $tags
     * @param  SchemaCollection<array-key>|null  $schema
     */
    private function build(
        string $title,
        ?string $description,
        ?string $image,
        ?string $author,
        string $type,
        ?string $robots = null,
        ?string $canonical = null,
        ?array $tags = null,
        ?string $section = null,
        ?CarbonInterface $publishedTime = null,
        ?CarbonInterface $modifiedTime = null,
        ?SchemaCollection $schema = null,
    ): SEOData {
        return new SEOData(
            title: $this->suffixedTitle($title),
            description: $description ?? $this->fallbackDescription(),
            author: $author,
            image: $image,
            // We bake the suffix into the title (above) and disable the SEO
            // package's own suffixing, which would otherwise also drag the
            // brand into og:title / twitter:title. Social cards instead read
            // the clean title from openGraphTitle below.
            enableTitleSuffix: false,
            published_time: $publishedTime,
            modified_time: $modifiedTime,
            section: $section,
            tags: $tags,
            twitter_username: $this->stringOrNull('laradocs.seo.twitter'),
            schema: $schema,
            type: $type,
            site_name: $this->siteName(),
            favicon: $this->stringOrNull('laradocs.ui.brand.favicon'),
            robots: $robots,
            canonical_url: $canonical,
            // Social cards read the clean, un-suffixed title.
            openGraphTitle: $title,
        );
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
        $trail = ['Home' => route('laradocs.index')];

        foreach (array_slice($breadcrumbs, 0, -1) as $crumb) {
            if ($crumb->isLink()) {
                $trail[$crumb->title] = route('laradocs.show', ['path' => $crumb->slug]);
            }
        }

        return $trail;
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

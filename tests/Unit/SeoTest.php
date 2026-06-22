<?php

declare(strict_types=1);

use Laradocs\Documents\TreeNode;
use Laradocs\Seo\Excerpt;
use Laradocs\Seo\SeoFactory;

const SEO_SITE_NAME = 'Acme Docs';
const SEO_SLUG = 'guide/intro';
const SEO_NOINDEX = 'noindex, nofollow';

describe('Excerpt', function () {
    it('lifts the first prose paragraph, skipping headings and code', function () {
        $markdown = <<<'MD'
        # A Heading

        ```php
        echo 'ignored';
        ```

        The first real sentence of the page.

        A later paragraph that should not appear.
        MD;

        expect(Excerpt::fromMarkdown($markdown))->toBe('The first real sentence of the page.');
    });

    it('strips inline markdown — links, images, emphasis and code', function () {
        $markdown = 'See the **bold** [guide](/docs/guide) and `code` here. ![alt](/x.png)';

        expect(Excerpt::fromMarkdown($markdown))->toBe('See the bold guide and code here.');
    });

    it('truncates to the requested length', function () {
        $excerpt = (string) Excerpt::fromMarkdown(str_repeat('word ', 100), 20);

        expect($excerpt)->toEndWith('...')
            ->and(mb_strlen($excerpt))->toBeLessThanOrEqual(25); // 20 chars + ellipsis
    });

    it('returns null when there is no prose', function () {
        expect(Excerpt::fromMarkdown("# Only a heading\n\n## And another"))->toBeNull()
            ->and(Excerpt::fromMarkdown(''))->toBeNull();
    });

    it('returns null when the opening paragraph reduces to nothing', function () {
        expect(Excerpt::fromMarkdown('![just an image](/diagram.png)'))->toBeNull();
    });

    it('skips a leading HTML comment', function () {
        expect(Excerpt::fromMarkdown("<!-- a note -->\n\nReal content here."))
            ->toBe('Real content here.');
    });
});

describe('SeoFactory', function () {
    beforeEach(function () {
        config()->set('laradocs.ui.brand.title', SEO_SITE_NAME);
    });

    it('builds rich SEO data from a document with sensible defaults', function () {
        $seo = app(SeoFactory::class)->forDocument(makeDocument(SEO_SLUG, [
            'title' => 'Intro',
            'description' => 'A short description.',
        ]));

        expect($seo->title)->toBe('Intro · ' . SEO_SITE_NAME) // brand suffix baked into <title>
            ->and($seo->openGraphTitle)->toBe('Intro')  // social cards stay clean
            ->and($seo->description)->toBe('A short description.')
            ->and($seo->type)->toBe('article')
            ->and($seo->site_name)->toBe(SEO_SITE_NAME)
            ->and($seo->enableTitleSuffix)->toBeFalse()
            ->and($seo->modified_time)->not->toBeNull()
            ->and($seo->schema)->not->toBeNull();
    });

    it('auto-generates a description from page content when none is set', function () {
        $seo = app(SeoFactory::class)->forDocument(makeDocument(
            SEO_SLUG,
            ['title' => 'Intro'],
            "# Intro\n\nLaradocs turns markdown into a polished docs site.",
        ));

        expect($seo->description)->toBe('Laradocs turns markdown into a polished docs site.');
    });

    it('honours a dedicated seo: front-matter block', function () {
        $seo = app(SeoFactory::class)->forDocument(makeDocument(SEO_SLUG, [
            'title' => 'Intro',
            'description' => 'Visible subtitle.',
            'seo' => [
                'title' => 'Custom SEO Title',
                'description' => 'Custom SEO description.',
                'image' => '/og/custom.png',
                'robots' => SEO_NOINDEX,
                'canonical' => 'https://example.com/canonical',
            ],
        ]));

        expect($seo->openGraphTitle)->toBe('Custom SEO Title')
            ->and($seo->description)->toBe('Custom SEO description.')
            ->and($seo->image)->toBe('/og/custom.png')
            ->and($seo->robots)->toBe(SEO_NOINDEX)
            ->and($seo->canonical_url)->toBe('https://example.com/canonical');
    });

    it('maps the noindex shorthand to a robots directive', function () {
        $seo = app(SeoFactory::class)->forDocument(makeDocument(SEO_SLUG, [
            'title' => 'Hidden',
            'noindex' => true,
        ]));

        expect($seo->robots)->toBe(SEO_NOINDEX);
    });

    it('treats a string noindex flag as truthy', function () {
        $seo = app(SeoFactory::class)->forDocument(makeDocument(SEO_SLUG, [
            'title' => 'Hidden',
            'noindex' => 'yes',
        ]));

        expect($seo->robots)->toBe(SEO_NOINDEX);
    });

    it('coerces a numeric front-matter value to a string', function () {
        $seo = app(SeoFactory::class)->forDocument(makeDocument(SEO_SLUG, [
            'title' => 'Intro',
            'seo' => ['section' => 2026],
        ]));

        expect($seo->section)->toBe('2026');
    });

    it('falls back to the brand tagline for the description', function () {
        config()->set('laradocs.ui.brand.tagline', 'Beautiful docs.');

        $seo = app(SeoFactory::class)->forDocument(makeDocument(SEO_SLUG, ['title' => 'Intro']));

        expect($seo->description)->toBe('Beautiful docs.');
    });

    it('exposes front-matter tags as article tags', function () {
        $seo = app(SeoFactory::class)->forDocument(makeDocument(SEO_SLUG, [
            'title' => 'Intro',
            'tags' => ['api', 'guide'],
        ]));

        expect($seo->tags)->toBe(['api', 'guide']);
    });

    it('builds a website payload for a page without a document', function () {
        $seo = app(SeoFactory::class)->forPage();

        expect($seo->title)->toBe(SEO_SITE_NAME)
            ->and($seo->type)->toBe('website');
    });

    it('omits the suffix when the title already is the site name', function () {
        $seo = app(SeoFactory::class)->forPage(SEO_SITE_NAME);

        expect($seo->title)->toBe(SEO_SITE_NAME);
    });

    it('lets an empty title_suffix disable the suffix entirely', function () {
        config()->set('laradocs.seo.title_suffix', '');

        $seo = app(SeoFactory::class)->forDocument(makeDocument(SEO_SLUG, ['title' => 'Intro']));

        expect($seo->title)->toBe('Intro');
    });

    it('leaves the description empty when auto-description is disabled', function () {
        config()->set('laradocs.seo.auto_description', false);

        $seo = app(SeoFactory::class)->forDocument(makeDocument(
            SEO_SLUG,
            ['title' => 'Intro'],
            "# Intro\n\nSome body text that would otherwise be used.",
        ));

        expect($seo->description)->toBeNull();
    });

    it('reads a robots default from config', function () {
        config()->set('laradocs.seo.robots', 'noindex');

        $seo = app(SeoFactory::class)->forDocument(makeDocument(SEO_SLUG, ['title' => 'Intro']));

        expect($seo->robots)->toBe('noindex');
    });

    it('parses a publication date from front-matter', function () {
        $seo = app(SeoFactory::class)->forDocument(makeDocument(SEO_SLUG, [
            'title' => 'Intro',
            'published_at' => '2026-01-15',
        ]));

        expect($seo->published_time)->not->toBeNull()
            ->and($seo->published_time->format('Y-m-d'))->toBe('2026-01-15');
    });

    it('ignores an unparseable publication date', function () {
        $seo = app(SeoFactory::class)->forDocument(makeDocument(SEO_SLUG, [
            'title' => 'Intro',
            'published_at' => 'not-a-real-date',
        ]));

        expect($seo->published_time)->toBeNull();
    });

    it('returns no schema when both schema types are disabled', function () {
        config()->set('laradocs.seo.schema.article', false);
        config()->set('laradocs.seo.schema.breadcrumbs', false);

        $seo = app(SeoFactory::class)->forDocument(makeDocument(SEO_SLUG, ['title' => 'Intro']));

        expect($seo->schema)->toBeNull();
    });

    it('defaults x_card to summary_large_image', function () {
        $factory = app(SeoFactory::class);
        $factory->forDocument(makeDocument(SEO_SLUG, ['title' => 'Intro']));

        expect($factory->xCard())->toBe('summary_large_image');
    });

    it('reads x_card from the top-level front-matter key', function () {
        $factory = app(SeoFactory::class);
        $factory->forDocument(makeDocument(SEO_SLUG, [
            'title' => 'Intro',
            'x_card' => 'summary',
        ]));

        expect($factory->xCard())->toBe('summary');
    });

    it('reads x_card from the seo: front-matter block', function () {
        $factory = app(SeoFactory::class);
        $factory->forDocument(makeDocument(SEO_SLUG, [
            'title' => 'Intro',
            'seo' => ['x_card' => 'summary'],
        ]));

        expect($factory->xCard())->toBe('summary');
    });

    it('prefers seo: block x_card over the top-level key', function () {
        $factory = app(SeoFactory::class);
        $factory->forDocument(makeDocument(SEO_SLUG, [
            'title' => 'Intro',
            'x_card' => 'summary',
            'seo' => ['x_card' => 'summary_large_image'],
        ]));

        expect($factory->xCard())->toBe('summary_large_image');
    });

    it('falls back to the config default for x_card', function () {
        config()->set('laradocs.seo.x_card', 'summary');

        $factory = app(SeoFactory::class);
        $factory->forDocument(makeDocument(SEO_SLUG, ['title' => 'Intro']));

        expect($factory->xCard())->toBe('summary');
    });

    it('resolves x_card for site pages (forPage)', function () {
        config()->set('laradocs.seo.x_card', 'summary');

        $factory = app(SeoFactory::class);
        $factory->forPage();

        expect($factory->xCard())->toBe('summary');
    });

    it('uses page image over site-wide default image', function () {
        config()->set('laradocs.seo.image', 'https://example.com/default.png');

        $seo = app(SeoFactory::class)->forDocument(makeDocument(SEO_SLUG, [
            'title' => 'Intro',
            'image' => 'https://example.com/page.png',
        ]));

        expect($seo->image)->toBe('https://example.com/page.png');
    });

    it('uses seo: block image over top-level image front-matter', function () {
        $seo = app(SeoFactory::class)->forDocument(makeDocument(SEO_SLUG, [
            'title' => 'Intro',
            'image' => 'https://example.com/top-level.png',
            'seo' => ['image' => 'https://example.com/seo-block.png'],
        ]));

        expect($seo->image)->toBe('https://example.com/seo-block.png');
    });

    it('falls back to site-wide image when no page image is set', function () {
        config()->set('laradocs.seo.image', 'https://example.com/site.png');

        $seo = app(SeoFactory::class)->forDocument(makeDocument(SEO_SLUG, ['title' => 'Intro']));

        expect($seo->image)->toBe('https://example.com/site.png');
    });

    it('leaves image null when no image is configured and generation is off', function () {
        config()->set('laradocs.seo.og_image.enabled', false);

        $seo = app(SeoFactory::class)->forDocument(makeDocument(SEO_SLUG, ['title' => 'Intro']));

        expect($seo->image)->toBeNull();
    });

    it('falls back to a generated card url when no image is configured', function () {
        $seo = app(SeoFactory::class)->forDocument(makeDocument(SEO_SLUG, ['title' => 'Intro']));

        expect($seo->image)->toContain('/docs/_laradocs/og/');
    });

    it('noindexes a non-default version page', function () {
        config()->set('laradocs.versions.enabled', true);
        config()->set('laradocs.versions.strategy', 'config');
        config()->set('laradocs.versions.default', 'v2');
        config()->set('laradocs.versions.available', [
            'v2' => ['label' => 'v2.0'],
            'v1' => ['label' => 'v1.0'],
        ]);
        config()->set('laradocs._current_version', 'v1');

        $seo = app(SeoFactory::class)->forDocument(makeDocument(SEO_SLUG, ['title' => 'Intro']));

        expect($seo->robots)->toBe('noindex, follow');
    });

    it('noindex+nofollows a deprecated version page', function () {
        config()->set('laradocs.versions.enabled', true);
        config()->set('laradocs.versions.strategy', 'config');
        config()->set('laradocs.versions.default', 'v2');
        config()->set('laradocs.versions.available', [
            'v2' => ['label' => 'v2.0'],
            'v1' => ['label' => 'v1.0', 'deprecated' => true],
        ]);
        config()->set('laradocs._current_version', 'v1');

        $seo = app(SeoFactory::class)->forDocument(makeDocument(SEO_SLUG, ['title' => 'Intro']));

        expect($seo->robots)->toBe(SEO_NOINDEX);
    });

    it('indexes the default version page normally', function () {
        config()->set('laradocs.versions.enabled', true);
        config()->set('laradocs.versions.strategy', 'config');
        config()->set('laradocs.versions.default', 'v2');
        config()->set('laradocs.versions.available', [
            'v2' => ['label' => 'v2.0'],
            'v1' => ['label' => 'v1.0'],
        ]);
        config()->set('laradocs._current_version', 'v2');

        $seo = app(SeoFactory::class)->forDocument(makeDocument(SEO_SLUG, ['title' => 'Intro']));

        expect($seo->robots)->toBeNull();
    });

    it('lets front-matter seo.robots override the automatic noindex', function () {
        config()->set('laradocs.versions.enabled', true);
        config()->set('laradocs.versions.strategy', 'config');
        config()->set('laradocs.versions.default', 'v2');
        config()->set('laradocs.versions.available', [
            'v2' => ['label' => 'v2.0'],
            'v1' => ['label' => 'v1.0'],
        ]);
        config()->set('laradocs._current_version', 'v1');

        $seo = app(SeoFactory::class)->forDocument(makeDocument(SEO_SLUG, [
            'title' => 'Intro',
            'seo' => ['robots' => 'index, follow'],
        ]));

        expect($seo->robots)->toBe('index, follow');
    });

    it('builds a breadcrumb trail from linked ancestors', function () {
        $ancestor = new TreeNode(
            title: 'Guide',
            slug: 'guide',
            document: makeDocument('guide', ['title' => 'Guide']),
        );
        $current = new TreeNode(
            title: 'Intro',
            slug: SEO_SLUG,
            document: makeDocument(SEO_SLUG, ['title' => 'Intro']),
        );

        $seo = app(SeoFactory::class)->forDocument(
            makeDocument(SEO_SLUG, ['title' => 'Intro']),
            [$ancestor, $current],
        );

        expect($seo->schema)->not->toBeNull();
    });
});

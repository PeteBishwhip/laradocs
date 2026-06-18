<?php

declare(strict_types=1);

namespace Laradocs\Seo;

use Laradocs\Contracts\OgImageGenerator;
use Laradocs\Documents\Document;
use Laradocs\Support\Config;

/**
 * The branding + content a {@see OgImageGenerator} needs to
 * render a page's social card.
 *
 * Construct it directly for full control, or use {@see self::fromDocument()} /
 * {@see self::forPage()} to derive sensible defaults from a document and the
 * site's configured theme (accent colour, logo, brand title).
 */
final readonly class OgImageData
{
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?string $url = null,
        public string $accentColor = '#FF2D20',
        public ?string $backgroundColor = null,
        public string $theme = 'light',
        public ?string $logo = null,
        public string $siteName = 'Documentation',
    ) {}

    /**
     * Build card data for a documentation page, lifting the description from
     * front-matter (falling back to the opening paragraph) and the branding
     * from the active Laradocs configuration.
     */
    public static function fromDocument(Document $document, ?string $url = null): self
    {
        return new self(
            ...self::brand(),
            title: $document->title(),
            description: self::descriptionFor($document),
            url: $url,
        );
    }

    /**
     * Build card data for a page without a backing document — the docs landing
     * page or the empty state.
     */
    public static function forPage(string $title, ?string $description = null, ?string $url = null): self
    {
        return new self(
            ...self::brand(),
            title: $title,
            description: $description,
            url: $url,
        );
    }

    private static function descriptionFor(Document $document): ?string
    {
        $description = $document->metadata->description;

        if ($description !== null && trim($description) !== '') {
            return $description;
        }

        return Excerpt::fromMarkdown($document->markdown);
    }

    /**
     * Branding pulled from the active configuration, ready to spread into the
     * constructor as named arguments.
     *
     * @return array{accentColor: string, backgroundColor: ?string, theme: string, logo: ?string, siteName: string}
     */
    private static function brand(): array
    {
        return [
            'accentColor' => Config::string('laradocs.ui.accent', '#FF2D20'),
            'backgroundColor' => Config::nullableString('laradocs.seo.og_image.background_color'),
            'theme' => Config::string('laradocs.seo.og_image.theme', 'light'),
            'logo' => Config::nullableString('laradocs.ui.brand.logo'),
            'siteName' => Config::nullableString('laradocs.seo.site_name')
                ?? Config::string('laradocs.ui.brand.title', 'Documentation'),
        ];
    }
}

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
final class OgImageData
{
    /**
     * @readonly
     * @var string
     */
    public $title;
    /**
     * @readonly
     * @var string|null
     */
    public $description;
    /**
     * @readonly
     * @var string|null
     */
    public $url;
    /**
     * @readonly
     * @var string
     */
    public $accentColor = '#FF2D20';
    /**
     * @readonly
     * @var string|null
     */
    public $backgroundColor;
    /**
     * @readonly
     * @var string
     */
    public $theme = 'light';
    /**
     * @readonly
     * @var string|null
     */
    public $logo;
    /**
     * @readonly
     * @var string
     */
    public $siteName = 'Documentation';
    /**
     * Dimensions of a default (the-og) social card. Exposed so the SEO layer can
     * advertise og:image:width / og:image:height for the generated card.
     * @var int
     */
    public const WIDTH = 1200;

    /**
     * @var int
     */
    public const HEIGHT = 630;

    public function __construct(string $title, ?string $description = null, ?string $url = null, string $accentColor = '#FF2D20', ?string $backgroundColor = null, string $theme = 'light', ?string $logo = null, string $siteName = 'Documentation')
    {
        $this->title = $title;
        $this->description = $description;
        $this->url = $url;
        $this->accentColor = $accentColor;
        $this->backgroundColor = $backgroundColor;
        $this->theme = $theme;
        $this->logo = $logo;
        $this->siteName = $siteName;
    }

    /**
     * Build card data for a documentation page, lifting the description from
     * front-matter (falling back to the opening paragraph) and the branding
     * from the active Laradocs configuration.
     */
    public static function fromDocument(Document $document, ?string $url = null): self
    {
        return new self($document->title(), self::descriptionFor($document), $url);
    }

    /**
     * Build card data for a page without a backing document — the docs landing
     * page or the empty state.
     */
    public static function forPage(string $title, ?string $description = null, ?string $url = null): self
    {
        return new self($title, $description, $url);
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

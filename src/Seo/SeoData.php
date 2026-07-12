<?php

declare(strict_types=1);

namespace Laradocs\Seo;

use DateTimeInterface;

/**
 * Small dependency-free SEO payload and renderer for the PHP 7.3 backport.
 */
final class SeoData
{
    /** @var array<string, mixed> */
    private $values;

    /** @param array<string, mixed> $values */
    public function __construct(array $values)
    {
        $this->values = $values;
    }

    /** @return mixed */
    public function __get(string $key)
    {
        return $this->get($key);
    }

    public function __isset(string $key): bool
    {
        return isset($this->values[$key]);
    }

    public function toHtml(): string
    {
        $title = $this->string('title');
        $socialTitle = $this->string('openGraphTitle') ?: $title;
        $description = $this->string('description');
        $image = $this->string('image');
        $type = $this->string('type') ?: 'website';
        $html = '<title>' . $this->escape($title) . '</title>';

        $html .= $this->meta('name', 'description', $description);
        $html .= $this->meta('name', 'author', $this->string('author'));
        $html .= $this->meta('name', 'robots', $this->string('robots'));
        $html .= $this->meta('property', 'og:title', $socialTitle);
        $html .= $this->meta('property', 'og:description', $description);
        $html .= $this->meta('property', 'og:type', $type);
        $html .= $this->meta('property', 'og:site_name', $this->string('site_name'));
        $html .= $this->meta('property', 'og:image', $image);
        $html .= $this->meta('name', 'twitter:title', $socialTitle);
        $html .= $this->meta('name', 'twitter:description', $description);
        $html .= $this->meta('name', 'twitter:image', $image);
        $html .= $this->meta('name', 'twitter:site', $this->string('twitter_username'));
        $html .= $this->meta('name', 'twitter:card', 'summary_large_image');

        $canonical = $this->string('canonical_url');
        if ($canonical === '' && function_exists('url')) {
            $canonical = url()->current();
        }
        if ($canonical !== '') {
            $html .= '<link rel="canonical" href="' . $this->escape($canonical) . '">';
        }

        $favicon = $this->string('favicon');
        if ($favicon !== '') {
            $html .= '<link rel="icon" href="' . $this->escape($favicon) . '">';
        }

        $html .= $this->dateMeta('article:published_time', $this->get('published_time'));
        $html .= $this->dateMeta('article:modified_time', $this->get('modified_time'));

        foreach ((array) $this->get('schema') as $schema) {
            $json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (is_string($json)) {
                $html .= '<script type="application/ld+json">' . $json . '</script>';
            }
        }

        return $html;
    }

    /** @return mixed */
    private function get(string $key)
    {
        return array_key_exists($key, $this->values) ? $this->values[$key] : null;
    }

    private function string(string $key): string
    {
        $value = $this->get($key);
        return is_scalar($value) ? (string) $value : '';
    }

    private function meta(string $attribute, string $name, string $content): string
    {
        return $content === '' ? '' : '<meta ' . $attribute . '="' . $this->escape($name) . '" content="' . $this->escape($content) . '">';
    }

    /** @param mixed $value */
    private function dateMeta(string $name, $value): string
    {
        return $value instanceof DateTimeInterface
            ? $this->meta('property', $name, $value->format(DateTimeInterface::ATOM))
            : '';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

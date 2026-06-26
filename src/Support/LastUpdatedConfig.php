<?php

declare(strict_types=1);

namespace Laradocs\Support;

use Carbon\CarbonImmutable;
use Closure;
use Laradocs\Documents\Document;

/**
 * Resolves the "last updated" display string for a document.
 *
 * Follows the same static-state pattern as {@see Locale} so that a resolver
 * registered at boot time (via a service provider) is visible to the view
 * layer on every subsequent request without any DI wiring.
 */
final class LastUpdatedConfig
{
    /**
     * Optional application-supplied callback that returns the display string
     * for a given document. When registered it takes priority over the
     * `laradocs.ui.last_updated_source` config value.
     *
     * Register via `LastUpdatedConfig::setResolver(fn (Document $doc) => ...)`
     * in a service provider. Pass `null` to clear and revert to config.
     */
    private static ?Closure $resolver = null;

    /**
     * Register a custom resolver for the "last updated" date.
     *
     * The closure receives the {@see Document} and must return a display string
     * or `null` (to hide the date entirely). Use this when the three built-in
     * source modes are not flexible enough:
     *
     *   LastUpdatedConfig::setResolver(fn (Document $doc) => $doc->metadata->updatedAt ?? 'Unknown');
     *   LastUpdatedConfig::setResolver(fn (Document $doc) => date('d M Y', $doc->modifiedAt));
     *
     * Pass `null` to clear a previously registered resolver.
     */
    public static function setResolver(?Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    /**
     * Resolve the "last updated" display string for a document.
     *
     * Resolution order:
     *   1. A custom closure registered via {@see setResolver()}.
     *   2. The `laradocs.ui.last_updated_source` config value:
     *      - `front_matter`          — front-matter `updated_at` only (default).
     *      - `mtime`                 — filesystem modification time only.
     *      - `front_matter_or_mtime` — front-matter with mtime as fallback.
     *
     * All built-in modes format dates using the `laradocs.locale.date_format`
     * config value (default `jS F Y`).
     *
     * Returns `null` when no date is available.
     */
    public static function resolve(Document $document): ?string
    {
        if (self::$resolver !== null) {
            $result = (self::$resolver)($document);

            return is_string($result) && $result !== '' ? $result : null;
        }

        $source = Config::string('laradocs.ui.last_updated_source', 'front_matter');
        $format = Config::string('laradocs.locale.date_format', 'jS F Y');
        $locale = (string) app()->getLocale();

        $mtimeCarbon = $document->modifiedAt > 0
            ? CarbonImmutable::createFromTimestamp($document->modifiedAt)
            : null;

        $render = static fn (?CarbonImmutable $carbon) => $carbon?->locale($locale)->translatedFormat($format);

        return match ($source) {
            'mtime' => $render($mtimeCarbon),
            'front_matter_or_mtime' => $render($document->metadata->updatedAtCarbon() ?? $mtimeCarbon),
            default => $render($document->metadata->updatedAtCarbon()),
        };
    }
}

<?php

declare(strict_types=1);

namespace Laradocs\Media;

use Illuminate\Support\Facades\URL;
use Laradocs\Routing\DocumentUrl;
use Laradocs\Support\Config;

/**
 * Builds the URL a resolved media path is served from.
 *
 * Signing is opt-in and answers hotlinking: the URL only works with a valid
 * signature, so it cannot be pasted onto another site and keep working. It is
 * applied per request rather than at render time — see {@see MediaRewriter} —
 * because rendered HTML is cached per file with no request in the key, and a
 * signature baked into that cache would either expire for everyone at once or
 * never expire at all.
 */
final class MediaUrl
{
    public static function for(string $path): string
    {
        $name = DocumentUrl::prefix() . 'media';
        $parameters = ['path' => $path];

        if (! self::signed()) {
            return route($name, $parameters);
        }

        $minutes = Config::nullableInt('laradocs.media.ttl');

        return $minutes === null
            ? URL::signedRoute($name, $parameters)
            : URL::temporarySignedRoute($name, now()->addMinutes($minutes), $parameters);
    }

    public static function signed(): bool
    {
        return Config::bool('laradocs.media.signed', false);
    }
}

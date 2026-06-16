<?php

declare(strict_types=1);

namespace Laradocs\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laradocs\Support\Config;
use Laradocs\Support\Version;
use Symfony\Component\HttpFoundation\Response;

/**
 * When multi-version docs are enabled, this middleware:
 *
 *  1. Detects which version the request targets from the first segment of the
 *     `{path}` route parameter (e.g. "v2" in /docs/v2/getting-started).
 *  2. Falls back to the configured default version when none is present.
 *  3. Records the resolved handle in `laradocs._current_version`, which the
 *     loader (via {@see Version::docsPath()}), CacheKey, DocumentUrl and
 *     DocsController all read to scope content, cache keys and URLs.
 *
 * The base `laradocs.docs.path` is never touched, so version auto-detection
 * keeps working mid-request. The runtime key is restored after the response
 * renders, keeping long-lived workers (Laravel Octane) free of state leakage.
 */
final class SetDocsVersion
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Config::bool('laradocs.versions.enabled', false)) {
            return $next($request);
        }

        $versions = Version::available();

        if ($versions === []) {
            return $next($request);
        }

        $previousVersion = Config::nullableString('laradocs._current_version');

        $detected = $this->detectVersion($request, $versions);
        $resolved = $detected ?? Version::default() ?? array_key_first($versions);

        config(['laradocs._current_version' => $resolved]);

        try {
            $response = $next($request);
        } finally {
            config(['laradocs._current_version' => $previousVersion]);
        }

        return $response;
    }

    /**
     * Extract a recognised version handle from the first segment of {path}.
     *
     * @param  array<string, string>  $versions
     */
    private function detectVersion(Request $request, array $versions): ?string
    {
        $path = ltrim((string) $request->route('path'), '/');
        $first = explode('/', $path)[0];

        return isset($versions[$first]) ? $first : null;
    }
}

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
 *  1. Detects which version the request targets by reading the first segment of
 *     the `{path}` route parameter (e.g. "v2" in /docs/v2/getting-started).
 *  2. Falls back to the configured default version when none is found.
 *  3. Rewrites `laradocs.docs.path` to the version's sub-directory so that the
 *     FilesystemLoader picks up the right content without any controller changes.
 *  4. Stores the resolved handle in `laradocs._current_version` so CacheKey and
 *     DocumentUrl can read it without re-interrogating the request.
 *  5. Strips the version segment from the `{path}` route parameter so that
 *     DocsController always receives a plain slug.
 *
 * Both mutations are restored after the response renders, keeping Octane
 * workers free from cross-request state leakage.
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

        if (empty($versions)) {
            return $next($request);
        }

        $previousPath = Config::string('laradocs.docs.path');
        $previousVersion = Config::nullableString('laradocs._current_version');

        $detected = $this->detectVersion($request, $versions);
        $resolved = $detected ?? Version::default() ?? (string) array_key_first($versions);

        config([
            'laradocs._current_version' => $resolved,
            'laradocs.docs.path' => Version::pathFor($resolved),
        ]);

        // Strip the version prefix from the {path} route parameter so that
        // DocsController::show() receives a clean slug.
        if ($request->route() !== null && $detected !== null) {
            $raw = ltrim((string) ($request->route('path') ?? ''), '/');
            $clean = ltrim(substr($raw, strlen($detected)), '/');
            $request->route()->setParameter('path', $clean);
        }

        try {
            $response = $next($request);
        } finally {
            config([
                'laradocs.docs.path' => $previousPath,
                'laradocs._current_version' => $previousVersion,
            ]);
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
        if ($request->route() === null) {
            return null;
        }

        $path = ltrim((string) ($request->route('path') ?? ''), '/');
        $first = explode('/', $path)[0];

        return isset($versions[$first]) ? $first : null;
    }
}

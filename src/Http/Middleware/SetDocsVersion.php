<?php

declare(strict_types=1);

namespace Laradocs\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laradocs\Routing\DocumentUrl;
use Laradocs\Support\Config;
use Laradocs\Support\Version;
use Symfony\Component\HttpFoundation\Response;

/**
 * When multi-version docs are enabled, this middleware resolves the first
 * segment of the `{path}` route parameter through an ordered chain so every URL
 * lands on a stable, crawlable version URL:
 *
 *  1. Versioning disabled → pass straight through.
 *  2. An alias segment (`latest`, `stable` or anything in `versions.aliases`)
 *     301-redirects to its canonical version URL.
 *  3. A known-but-hidden version key 404s — hidden versions are reachable only
 *     via redirects, never directly.
 *  4. A known, non-hidden version key activates that version.
 *  5. An unrecognised or absent segment applies the `versions.unversioned`
 *     policy: "redirect" 301s to the default version's URL, "render" activates
 *     the default version silently in place.
 *
 * The resolved handle is recorded in `laradocs._current_version`, which the
 * loader (via {@see Version::docsPath()}), CacheKey, DocumentUrl and
 * DocsController all read to scope content, cache keys and URLs. The base
 * `laradocs.docs.path` is never touched, so version auto-detection keeps working
 * mid-request. The runtime key is restored after the response renders, keeping
 * long-lived workers (Laravel Octane) free of state leakage.
 */
final class SetDocsVersion
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $versions = Version::available();

        if (! Config::bool('laradocs.versions.enabled', false) || $versions === []) {
            return $next($request);
        }

        return $this->resolveVersion($request, $next, $versions);
    }

    /**
     * Walk the resolution chain once we know versioning is active and versions
     * exist: alias → 301, known version → activate, unversioned → policy.
     *
     * @param  array<string, string>  $versions
     * @param  Closure(Request): Response  $next
     */
    private function resolveVersion(Request $request, Closure $next, array $versions): Response
    {
        $registry = Version::registry();
        $path = ltrim((string) $request->route('path'), '/');
        $segment = explode('/', $path)[0];

        // Alias (latest / stable / configured) → 301 to its canonical version.
        if ($segment !== '' && $registry->isAlias($segment)) {
            $canonical = $registry->resolveAlias($segment);

            if ($canonical !== null) {
                return $this->redirect($canonical, implode('/', array_slice(explode('/', $path), 1)));
            }
        }

        // A known version key: hidden versions 404, others activate in place.
        if ($segment !== '') {
            $info = $registry->get($segment);

            if ($info !== null) {
                if ($info->hidden) {
                    abort(404);
                }

                return $this->activate($request, $next, $segment);
            }
        }

        // Unrecognised or absent segment: apply the unversioned policy.
        $default = Version::default() ?? (string) array_key_first($versions);

        return Config::string('laradocs.versions.unversioned', 'redirect') === 'redirect'
            ? $this->redirect($default, $path)
            : $this->activate($request, $next, $default);
    }

    /**
     * Activate a version for the duration of the request, restoring the previous
     * runtime handle afterwards so long-lived workers stay state-free.
     *
     * @param  Closure(Request): Response  $next
     */
    private function activate(Request $request, Closure $next, string $version): Response
    {
        $previous = Config::nullableString('laradocs._current_version');

        config(['laradocs._current_version' => $version]);

        try {
            return $next($request);
        } finally {
            config(['laradocs._current_version' => $previous]);
        }
    }

    /**
     * 301-redirect to the canonical URL for $version, carrying $slug as the
     * rest of the path (empty for a bare version root).
     */
    private function redirect(string $version, string $slug): Response
    {
        return redirect()->to(DocumentUrl::forVersion($slug, $version), 301);
    }
}

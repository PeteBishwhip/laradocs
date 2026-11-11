<?php

declare(strict_types=1);

namespace Laradocs\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Activates the default docs version in place for fixed-path artifact routes —
 * sitemap.xml, feed.xml, the search and tag endpoints — that carry no `{path}`
 * to resolve a version from and can't sensibly redirect. See the `render`
 * behaviour documented on {@see SetDocsVersion}.
 *
 * These routes drop the group's {@see SetDocsVersion} with `withoutMiddleware()`
 * and add this instead. It is deliberately a separate class rather than
 * `SetDocsVersion::class . ':render'`: since Laravel 13.42, excluding a
 * middleware class also excludes every parameterised variant of it (and its
 * subclasses), which silently stripped the `:render` variant and left these
 * routes unscoped.
 */
final class SetDefaultDocsVersion
{
    public function __construct(private readonly SetDocsVersion $versions) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $this->versions->handle($request, $next, 'render');
    }
}

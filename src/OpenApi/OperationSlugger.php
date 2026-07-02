<?php

declare(strict_types=1);

namespace Laradocs\OpenApi;

use Illuminate\Support\Str;

/**
 * Derives stable, human-readable URL slugs for a spec's operations.
 *
 * The slug is `{baseSlug}/{tag}/{segment}`, where the segment prefers the
 * operation's summary (so the URL matches the page title, e.g.
 * `list-background-processes`) and falls back to the operationId, then the
 * method + path. Summaries are not unique, so collisions within a spec gain a
 * stable numeric suffix in declaration order.
 *
 * Both the loader (which mounts the pages) and the overview renderer (which
 * links to them) map through this class, so their URLs can never drift apart.
 */
final class OperationSlugger
{
    /**
     * Map every operation to its slug, keyed by {@see self::identity()}.
     *
     * @param  array<int, Operation>  $operations
     * @return array<string, string>
     */
    public static function map(array $operations, string $baseSlug): array
    {
        $used = [];
        $slugs = [];

        foreach ($operations as $operation) {
            $base = $baseSlug . '/' . Str::slug($operation->tags[0] ?? 'default') . '/' . self::segment($operation);
            $slugs[self::identity($operation)] = self::unique($base, $used);
        }

        return $slugs;
    }

    /**
     * A stable key identifying an operation within a spec (method + path is
     * unique per OpenAPI).
     */
    public static function identity(Operation $operation): string
    {
        return strtoupper($operation->method) . ' ' . $operation->path;
    }

    /**
     * The final path segment: the first of summary / operationId / method+path
     * that yields a non-empty slug.
     */
    private static function segment(Operation $operation): string
    {
        $candidates = [
            $operation->summary,
            $operation->operationId,
            $operation->method . ' ' . $operation->path,
        ];

        foreach ($candidates as $candidate) {
            $slug = Str::slug((string) ($candidate ?? ''));

            if ($slug !== '') {
                return $slug;
            }
        }

        return 'operation';
    }

    /**
     * @param  array<string, true>  $used
     */
    private static function unique(string $slug, array &$used): string
    {
        $candidate = $slug;

        for ($i = 2; isset($used[$candidate]); $i++) {
            $candidate = $slug . '-' . $i;
        }

        $used[$candidate] = true;

        return $candidate;
    }
}

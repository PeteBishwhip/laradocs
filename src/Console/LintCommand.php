<?php

declare(strict_types=1);

namespace Laradocs\Console;

use DateTime;
use Illuminate\Console\Command;
use Laradocs\Documents\Document;
use Laradocs\Documents\DocumentCollection;
use Laradocs\Laradocs;
use Laradocs\Support\Config;

final class LintCommand extends Command
{
    protected $signature = 'docs:lint {--json : Output results as JSON}';

    protected $description = 'Validate front-matter: required fields, slug collisions, layout names, and date formats';

    public function handle(Laradocs $laradocs): int
    {
        $documents = $laradocs->all();

        /** @var array<int, string> $required */
        $required = Config::array('laradocs.lint.required', ['title']);

        /** @var array<int, string> $layouts */
        $layouts = Config::array('laradocs.lint.layouts', []);

        $missingFields = $this->checkRequiredFields($documents, $required);
        $slugCollisions = $this->checkSlugCollisions($documents);
        $unknownLayouts = $this->checkLayouts($documents, $layouts);
        $invalidDates = $this->checkDates($documents);

        $total = count($missingFields) + count($slugCollisions) + count($unknownLayouts) + count($invalidDates);

        if ($this->option('json')) {
            $this->line(json_encode([
                'missing_fields' => $missingFields,
                'slug_collisions' => $slugCollisions,
                'unknown_layouts' => $unknownLayouts,
                'invalid_dates' => $invalidDates,
                'summary' => [
                    'missing_fields' => count($missingFields),
                    'slug_collisions' => count($slugCollisions),
                    'unknown_layouts' => count($unknownLayouts),
                    'invalid_dates' => count($invalidDates),
                    'total' => $total,
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return $total > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->renderFindings($missingFields, $slugCollisions, $unknownLayouts, $invalidDates);

        if ($total === 0) {
            $this->components->info('All lint checks passed.');
        }

        return $total > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Warn for every document that is missing one of the configured required
     * front-matter fields. Uses snake_case YAML key names (e.g. "updated_at").
     *
     * @param  DocumentCollection<int, Document>  $documents
     * @param  array<int, string>  $required
     * @return array<int, array{slug: string, path: string, field: string}>
     */
    private function checkRequiredFields(DocumentCollection $documents, array $required): array
    {
        if ($required === []) {
            return [];
        }

        $findings = [];

        foreach ($documents as $document) {
            $meta = $document->metadata->toArray();

            foreach ($required as $field) {
                $value = $meta[$field] ?? null;

                $missing = $value === null
                    || $value === ''
                    || (is_array($value) && $value === []);

                if ($missing) {
                    $findings[] = [
                        'slug' => $document->slug,
                        'path' => $document->relativePath,
                        'field' => $field,
                    ];
                }
            }
        }

        return $findings;
    }

    /**
     * Report every slug that is shared by more than one document. This can
     * happen when a metadata "slug:" override clashes with another file's
     * path-derived slug, or when two files share an identical path stem.
     *
     * @param  DocumentCollection<int, Document>  $documents
     * @return array<int, array{slug: string, paths: array<int, string>}>
     */
    private function checkSlugCollisions(DocumentCollection $documents): array
    {
        /** @var array<string, array<int, string>> $seen */
        $seen = [];

        foreach ($documents as $document) {
            $seen[$document->slug][] = $document->relativePath;
        }

        $findings = [];

        foreach ($seen as $slug => $paths) {
            if (count($paths) > 1) {
                $findings[] = ['slug' => $slug, 'paths' => array_values($paths)];
            }
        }

        return $findings;
    }

    /**
     * When laradocs.lint.layouts is non-empty, warn for any document whose
     * "layout" front-matter value is not in that allowlist.
     *
     * @param  DocumentCollection<int, Document>  $documents
     * @param  array<int, string>  $layouts  Allowlist; empty disables the check.
     * @return array<int, array{slug: string, path: string, layout: string}>
     */
    private function checkLayouts(DocumentCollection $documents, array $layouts): array
    {
        if ($layouts === []) {
            return [];
        }

        $findings = [];

        foreach ($documents as $document) {
            $layout = $document->metadata->layout;

            if ($layout === null) {
                continue;
            }

            if (! in_array($layout, $layouts, true)) {
                $findings[] = [
                    'slug' => $document->slug,
                    'path' => $document->relativePath,
                    'layout' => $layout,
                ];
            }
        }

        return $findings;
    }

    /**
     * Warn for every document whose "updated_at" value is present but cannot
     * be parsed as a recognised date/datetime format.
     *
     * Accepted formats: Y-m-d, Y-m-d H:i:s, Y-m-d\TH:i:s, Y-m-d\TH:i:sP
     *
     * @param  DocumentCollection<int, Document>  $documents
     * @return array<int, array{slug: string, path: string, value: string}>
     */
    private function checkDates(DocumentCollection $documents): array
    {
        $findings = [];

        foreach ($documents as $document) {
            $value = $document->metadata->updatedAt;

            if ($value === null) {
                continue;
            }

            if (! $this->isValidDate($value)) {
                $findings[] = [
                    'slug' => $document->slug,
                    'path' => $document->relativePath,
                    'value' => $value,
                ];
            }
        }

        return $findings;
    }

    private function isValidDate(string $value): bool
    {
        // YAML 1.1 silently converts bare date scalars (e.g. 2026-01-15) to
        // Unix timestamps before we ever see them; a purely numeric string is
        // therefore the result of a validly-formatted YAML date.
        if (ctype_digit($value)) {
            return true;
        }

        foreach (['Y-m-d', 'Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i:sP'] as $format) {
            $dt = DateTime::createFromFormat($format, $value);

            if ($dt !== false && $dt->format($format) === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array{slug: string, path: string, field: string}>  $missingFields
     * @param  array<int, array{slug: string, paths: array<int, string>}>  $slugCollisions
     * @param  array<int, array{slug: string, path: string, layout: string}>  $unknownLayouts
     * @param  array<int, array{slug: string, path: string, value: string}>  $invalidDates
     */
    private function renderFindings(
        array $missingFields,
        array $slugCollisions,
        array $unknownLayouts,
        array $invalidDates,
    ): void {
        foreach ($missingFields as $finding) {
            $this->components->twoColumnDetail(
                '<fg=red>MISSING FIELD</>',
                sprintf('%s  <fg=gray>"%s" missing  (%s)</>', $finding['slug'], $finding['field'], $finding['path']),
            );
        }

        foreach ($slugCollisions as $collision) {
            $this->components->twoColumnDetail(
                '<fg=red>SLUG COLLISION</>',
                sprintf('%s  <fg=gray>%s</>', $collision['slug'], implode(', ', $collision['paths'])),
            );
        }

        foreach ($unknownLayouts as $finding) {
            $this->components->twoColumnDetail(
                '<fg=yellow>UNKNOWN LAYOUT</>',
                sprintf('%s  <fg=gray>"%s" (%s)</>', $finding['slug'], $finding['layout'], $finding['path']),
            );
        }

        foreach ($invalidDates as $finding) {
            $this->components->twoColumnDetail(
                '<fg=red>INVALID DATE</>',
                sprintf('%s  <fg=gray>updated_at "%s" (%s)</>', $finding['slug'], $finding['value'], $finding['path']),
            );
        }
    }
}

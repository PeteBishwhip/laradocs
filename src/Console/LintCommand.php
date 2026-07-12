<?php

declare(strict_types=1);

namespace Laradocs\Console;

use DateTime;
use Illuminate\Console\Command;
use Laradocs\Documents\Document;
use Laradocs\Documents\DocumentCollection;
use Laradocs\Extensions\IconExtension;
use Laradocs\Icons\IconReference;
use Laradocs\Icons\IconRegistry;
use Laradocs\Laradocs;
use Laradocs\Support\CodeAwareReplacer;
use Laradocs\Support\Config;

final class LintCommand extends Command
{
    protected $signature = 'docs:lint {--json : Output results as JSON}';

    protected $description = 'Validate front-matter: required fields, slug collisions, layout names, date formats, and icon references';

    public function handle(Laradocs $laradocs, IconRegistry $icons): int
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
        $unresolvedIcons = Config::bool('laradocs.lint.icons', true)
            ? $this->checkIcons($documents, $icons)
            : [];

        $total = count($missingFields) + count($slugCollisions) + count($unknownLayouts)
            + count($invalidDates) + count($unresolvedIcons);

        if ($this->option('json')) {
            $this->line(json_encode([
                'missing_fields' => $missingFields,
                'slug_collisions' => $slugCollisions,
                'unknown_layouts' => $unknownLayouts,
                'invalid_dates' => $invalidDates,
                'unresolved_icons' => $unresolvedIcons,
                'summary' => [
                    'missing_fields' => count($missingFields),
                    'slug_collisions' => count($slugCollisions),
                    'unknown_layouts' => count($unknownLayouts),
                    'invalid_dates' => count($invalidDates),
                    'unresolved_icons' => count($unresolvedIcons),
                    'total' => $total,
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return $total > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->renderFindings($missingFields, $slugCollisions, $unknownLayouts, $invalidDates, $unresolvedIcons);

        if ($total === 0) {
            $this->info('All lint checks passed.');
        }

        return $total > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Warn for every document that is missing one of the configured required
     * front-matter fields. Uses snake_case YAML key names (e.g. "updated_at").
     *
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
     * Flag every icon reference — the front-matter "icon:" field and inline
     * icon() shorthand calls in the body — that does not resolve to an SVG.
     *
     * This is what surfaces a missing icon dependency: a built-in icon set such
     * as "heroicons" is only present once its npm package is installed, so a
     * deployment that uses icons without it would otherwise render nothing
     * silently. The "reason" distinguishes an absent set (install the package)
     * from an unknown icon name (a typo or removed glyph).
     *
     * @return array<int, array{slug: string, path: string, icon: string, set: string, reason: string}>
     */
    private function checkIcons(DocumentCollection $documents, IconRegistry $icons): array
    {
        $defaultVariant = Config::string('laradocs.icons.heroicons.variant', 'outline');
        $findings = [];

        foreach ($documents as $document) {
            foreach ($this->collectIconReferences($document, $defaultVariant) as $reference) {
                if ($reference->name === '' || $icons->resolves($reference->name, $reference->variant, $reference->set)) {
                    continue;
                }

                $set = $reference->set ?? $icons->getDefaultSet();

                $findings[] = [
                    'slug' => $document->slug,
                    'path' => $document->relativePath,
                    'icon' => $reference->name,
                    'set' => $set,
                    'reason' => $icons->has($set) ? 'unknown_icon' : 'set_unavailable',
                ];
            }
        }

        return $findings;
    }

    /**
     * Gather every icon a document references: the front-matter "icon:" field
     * plus inline @icon(...) calls in the body (skipping code blocks, just as
     * the renderer does, so documented examples are not flagged).
     *
     * @return array<int, IconReference>
     */
    private function collectIconReferences(Document $document, string $defaultVariant): array
    {
        $references = [];

        if (($icon = $document->metadata->icon) !== null && $icon !== '') {
            $references[] = new IconReference($icon, $defaultVariant, null);
        }

        CodeAwareReplacer::apply($document->markdown, function (string $text) use (&$references, $defaultVariant): string {
            if (preg_match_all(IconExtension::CALL_PATTERN, $text, $matches) > 0) {
                foreach ($matches[1] as $inner) {
                    $references[] = IconReference::parse($inner, $defaultVariant);
                }
            }

            return $text;
        });

        return $references;
    }

    /**
     * @param  array<int, array{slug: string, path: string, field: string}>  $missingFields
     * @param  array<int, array{slug: string, paths: array<int, string>}>  $slugCollisions
     * @param  array<int, array{slug: string, path: string, layout: string}>  $unknownLayouts
     * @param  array<int, array{slug: string, path: string, value: string}>  $invalidDates
     * @param  array<int, array{slug: string, path: string, icon: string, set: string, reason: string}>  $unresolvedIcons
     */
    private function renderFindings(
        array $missingFields,
        array $slugCollisions,
        array $unknownLayouts,
        array $invalidDates,
        array $unresolvedIcons
    ): void {
        foreach ($missingFields as $finding) {
            $this->twoColumnDetail(
                '<fg=red>MISSING FIELD</>',
                sprintf('%s  <fg=gray>"%s" missing  (%s)</>', $finding['slug'], $finding['field'], $finding['path']),
            );
        }

        foreach ($slugCollisions as $collision) {
            $this->twoColumnDetail(
                '<fg=red>SLUG COLLISION</>',
                sprintf('%s  <fg=gray>%s</>', $collision['slug'], implode(', ', $collision['paths'])),
            );
        }

        foreach ($unknownLayouts as $finding) {
            $this->twoColumnDetail(
                '<fg=yellow>UNKNOWN LAYOUT</>',
                sprintf('%s  <fg=gray>"%s" (%s)</>', $finding['slug'], $finding['layout'], $finding['path']),
            );
        }

        foreach ($invalidDates as $finding) {
            $this->twoColumnDetail(
                '<fg=red>INVALID DATE</>',
                sprintf('%s  <fg=gray>updated_at "%s" (%s)</>', $finding['slug'], $finding['value'], $finding['path']),
            );
        }

        foreach ($unresolvedIcons as $finding) {
            $this->twoColumnDetail(
                '<fg=red>UNRESOLVED ICON</>',
                sprintf('%s  <fg=gray>%s (%s)</>', $finding['slug'], $this->iconHint($finding), $finding['path']),
            );
        }
    }

    private function twoColumnDetail(string $label, string $detail): void
    {
        $this->line($label . '  ' . $detail);
    }

    /**
     * A human-readable explanation for an unresolved icon finding, hinting at
     * the npm install for the bundled heroicons set when its files are absent.
     *
     * @param  array{slug: string, path: string, icon: string, set: string, reason: string}  $finding
     */
    private function iconHint(array $finding): string
    {
        if ($finding['reason'] === 'set_unavailable') {
            $install = $finding['set'] === 'heroicons'
                ? ' — run "npm install heroicons"'
                : '';

            return sprintf('"%s" icon set "%s" is not available%s', $finding['icon'], $finding['set'], $install);
        }

        return sprintf('"%s" not found in icon set "%s"', $finding['icon'], $finding['set']);
    }
}

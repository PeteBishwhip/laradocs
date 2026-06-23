<?php

declare(strict_types=1);

namespace Laradocs\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Laravel\Prompts\FormBuilder;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\form;
use function Laravel\Prompts\text;

final class LangCommand extends Command
{
    protected $signature = 'laradocs:lang {locale? : The locale code to scaffold, e.g. fr}
        {--list : List bundled and published locales}
        {--force : Overwrite an existing locale file}
        {--translate : Start translating strings immediately after scaffolding}';

    protected $description = 'Scaffold a translation file for a new locale, or list available locales';

    private const PACKAGE_LANG = __DIR__ . '/../../resources/lang';

    public function handle(Filesystem $files): int
    {
        if ($this->option('list')) {
            return $this->listLocales($files);
        }

        $locale = $this->argument('locale');

        if (! is_string($locale) || $locale === '') {
            $this->components->error('Provide a locale code (e.g. php artisan laradocs:lang fr) or use --list.');

            return self::FAILURE;
        }

        return $this->scaffoldLocale($locale, $files);
    }

    private function scaffoldLocale(string $locale, Filesystem $files): int
    {
        $target = lang_path('vendor/laradocs/' . $locale . '/laradocs.php');

        if ($files->exists($target) && ! $this->option('force')) {
            $this->components->error('Translation file already exists: ' . $target);
            $this->components->info('Use --force to overwrite.');

            return self::FAILURE;
        }

        $source = $this->sourceFile($locale, $files);

        $files->ensureDirectoryExists(dirname($target));
        $files->copy($source, $target);

        $usingBundled = $source === self::PACKAGE_LANG . '/' . $locale . '/laradocs.php';

        if (! $usingBundled) {
            $this->components->info('The file contains English strings. Translate each value to complete the ' . $locale . ' locale.');
        }

        if ($this->shouldTranslate()) {
            $this->translateStrings($target, $source);
        }

        $this->components->info('File: ' . $target);

        return self::SUCCESS;
    }

    /**
     * Whether the translation loop should run.
     *
     * True when --translate is passed, or when the terminal is interactive
     * and the user confirms the prompt. In non-interactive contexts (CI,
     * piped input, test harness without --translate) the question is never
     * asked and translation is skipped.
     */
    private function shouldTranslate(): bool
    {
        if ($this->option('translate')) {
            return true;
        }

        // Only ask in a real interactive terminal. stream_isatty() is false
        // when piped, in CI, or under the test harness — skip silently there.
        if (! (defined('STDIN') && stream_isatty(STDIN))) {
            return false;
        }

        return confirm('Would you like to translate the strings now?', default: false);
    }

    private function translateStrings(string $target, string $source): void
    {
        /** @var array<string, mixed> $strings */
        $strings = require $target;

        $flat = $this->flatten($strings);

        if ($flat === []) {
            return;
        }

        $builder = form();

        foreach ($flat as $key => $original) {
            $builder->add(
                fn (array $responses, mixed $prev) => text(
                    label: $key,
                    default: is_string($prev) ? $prev : $original,
                    hint: 'Original: ' . $original . '  (press Enter to keep)',
                ),
                name: $key,
            );
        }

        /** @var array<string, string> $responses */
        $responses = $builder->submit();

        $translated = $this->unflatten($responses);

        $this->writePhpFile($target, $translated, (string) file_get_contents($source));
    }

    private function writePhpFile(string $path, array $data, string $sourceContent): void
    {
        $commentEnd = strrpos($sourceContent, '*/');

        $header = $commentEnd !== false
            ? substr($sourceContent, 0, $commentEnd + 2) . "\n\n"
            : "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n\n";

        file_put_contents($path, $header . $this->formatArray($data) . "];\n");
    }

    private function formatArray(array $data, int $depth = 1): string
    {
        $pad = str_repeat('    ', $depth);
        $lines = [];

        foreach ($data as $key => $value) {
            $k = "'" . addcslashes((string) $key, "'\\") . "'";

            if (is_array($value)) {
                $lines[] = $pad . $k . ' => [';
                $lines[] = $this->formatArray($value, $depth + 1);
                $lines[] = $pad . '],';
            } else {
                $v = "'" . addcslashes((string) $value, "'\\") . "'";
                $lines[] = $pad . $k . ' => ' . $v . ',';
            }

            if ($depth === 1) {
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Flatten a nested array to dot-notation leaf paths.
     *
     * @return array<string, string>
     */
    private function flatten(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $path = $prefix !== '' ? $prefix . '.' . $key : (string) $key;

            if (is_array($value)) {
                $result = array_merge($result, $this->flatten($value, $path));
            } else {
                $result[$path] = (string) $value;
            }
        }

        return $result;
    }

    /**
     * Rebuild a nested array from dot-notation paths.
     *
     * @param  array<string, string>  $flat
     * @return array<string, mixed>
     */
    private function unflatten(array $flat): array
    {
        $result = [];

        foreach ($flat as $path => $value) {
            $this->setNested($result, explode('.', $path), $value);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $array
     * @param  list<string>  $keys
     */
    private function setNested(array &$array, array $keys, string $value): void
    {
        $key = array_shift($keys);

        if ($keys === []) {
            $array[$key] = $value;
        } else {
            if (! isset($array[$key]) || ! is_array($array[$key])) {
                $array[$key] = [];
            }

            $this->setNested($array[$key], $keys, $value);
        }
    }

    /**
     * Resolve the best source file for the given locale.
     *
     * Priority:
     *   1. A bundled package translation for the exact locale.
     *   2. The app's published English file.
     *   3. The package's bundled English file.
     */
    private function sourceFile(string $locale, Filesystem $files): string
    {
        $bundled = self::PACKAGE_LANG . '/' . $locale . '/laradocs.php';

        if (is_file($bundled)) {
            return $bundled;
        }

        $publishedEn = lang_path('vendor/laradocs/en/laradocs.php');

        if ($files->exists($publishedEn)) {
            return $publishedEn;
        }

        return self::PACKAGE_LANG . '/en/laradocs.php';
    }

    private function listLocales(Filesystem $files): int
    {
        $bundled = $this->bundledLocales();
        $published = $this->publishedLocales($files);

        $all = array_unique(array_merge($bundled, $published));
        sort($all);

        $rows = array_map(
            fn (string $code): array => [
                $code,
                in_array($code, $bundled, true) ? 'yes' : 'no',
                in_array($code, $published, true) ? 'yes' : 'no',
            ],
            $all,
        );

        $this->table(['locale', 'bundled', 'published'], $rows);

        $missing = array_values(array_filter($bundled, fn (string $c): bool => ! in_array($c, $published, true)));

        if ($missing !== []) {
            $this->components->info('Run `php artisan laradocs:lang <locale>` to scaffold a missing locale.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function bundledLocales(): array
    {
        $path = self::PACKAGE_LANG;
        $locales = [];

        foreach (array_diff((array) scandir($path), ['.', '..']) as $entry) {
            $entry = (string) $entry;

            if (is_dir($path . '/' . $entry)) {
                $locales[] = $entry;
            }
        }

        sort($locales);

        return $locales;
    }

    /**
     * @return array<int, string>
     */
    private function publishedLocales(Filesystem $files): array
    {
        $path = lang_path('vendor/laradocs');
        $locales = [];

        if (! $files->isDirectory($path)) {
            return [];
        }

        foreach (array_diff((array) scandir($path), ['.', '..']) as $entry) {
            $entry = (string) $entry;

            if (is_dir($path . '/' . $entry)) {
                $locales[] = $entry;
            }
        }

        sort($locales);

        return $locales;
    }
}

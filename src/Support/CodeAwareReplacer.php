<?php

declare(strict_types=1);

namespace Laradocs\Support;

use Closure;

/**
 * Applies a transformation to markdown while leaving fenced code blocks and
 * inline code spans untouched — so documenting literal {{ }} or @docs() syntax
 * inside code examples does not trigger interpolation.
 */
final class CodeAwareReplacer
{
    /**
     * @param  Closure(string): string  $callback
     */
    public static function apply(string $markdown, Closure $callback): string
    {
        $lines = explode("\n", $markdown);
        $output = [];
        $fence = null;

        foreach ($lines as $line) {
            if ($fence === null && preg_match('/^\s{0,3}(`{3,}|~{3,})/', $line, $m) === 1) {
                $fence = $m[1];
                $output[] = $line;

                continue;
            }

            if ($fence !== null) {
                $output[] = $line;

                // A closing fence must use the same character and be at least
                // as long as the opener (CommonMark §4.5), with only whitespace.
                $closer = '/^\s{0,3}' . $fence[0] . '{' . strlen($fence) . ',}\s*$/';

                if (preg_match($closer, $line) === 1) {
                    $fence = null;
                }

                continue;
            }

            $output[] = self::applyToInline($line, $callback);
        }

        return implode("\n", $output);
    }

    /**
     * Transform a line outside fenced code, leaving inline code spans intact.
     * A code span opens with a run of N backticks and closes at the next run
     * of exactly N backticks (CommonMark §6.1).
     *
     * @param  Closure(string): string  $callback
     */
    private static function applyToInline(string $line, Closure $callback): string
    {
        $result = '';
        $textStart = 0;
        $i = 0;
        $length = strlen($line);

        while ($i < $length) {
            if ($line[$i] !== '`') {
                $i++;

                continue;
            }

            $runStart = $i;
            while ($i < $length && $line[$i] === '`') {
                $i++;
            }
            $runLength = $i - $runStart;

            $close = self::findClosingRun($line, $i, $runLength);

            if ($close === null) {
                // Unbalanced run — treat as ordinary text and keep scanning.
                continue;
            }

            $result .= $callback(substr($line, $textStart, $runStart - $textStart));
            $result .= substr($line, $runStart, ($close + $runLength) - $runStart);
            $i = $close + $runLength;
            $textStart = $i;
        }

        return $result . $callback(substr($line, $textStart));
    }

    /**
     * Find the start index of the next run of exactly $length backticks.
     */
    private static function findClosingRun(string $line, int $from, int $length): ?int
    {
        $i = $from;
        $end = strlen($line);

        while ($i < $end) {
            if ($line[$i] !== '`') {
                $i++;

                continue;
            }

            $runStart = $i;
            while ($i < $end && $line[$i] === '`') {
                $i++;
            }

            if (($i - $runStart) === $length) {
                return $runStart;
            }
        }

        return null;
    }
}

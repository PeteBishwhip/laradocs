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
     * Mask fenced code blocks and inline code spans with opaque placeholders so
     * a transform can run over the whole document at once — across line
     * boundaries — without ever touching code. Returns the masked string and a
     * restorer that swaps the placeholders back for the original code.
     *
     * Unlike {@see apply()}, which hands the callback one line at a time, this
     * is the right primitive for multi-line constructs (e.g. a block component
     * whose opening and closing tags sit on different lines).
     *
     * An `$except` predicate receives each opening fence line and may keep that
     * block unmasked — for a transform that must operate on fenced blocks
     * itself, while still being shielded from the ones nested inside a longer
     * fence.
     *
     * @param  ?Closure(string): bool  $except
     * @return array{0: string, 1: Closure(string): string}
     */
    public static function protect(string $markdown, ?Closure $except = null): array
    {
        $placeholders = [];
        // \x1F rather than \x00: NUL is in PHP's default trim charlist, so a
        // caller that trims masked text would shear the delimiter off and leave
        // the placeholder unrestorable.
        $token = static function (string $code) use (&$placeholders): string {
            $key = "\x1Flaradocs-code-" . count($placeholders) . "\x1F";
            $placeholders[$key] = $code;

            return $key;
        };

        $lines = explode("\n", $markdown);
        $output = [];
        $fence = null;
        $buffer = [];
        $keep = false;

        foreach ($lines as $line) {
            if ($fence === null && preg_match('/^\s{0,3}(`{3,}|~{3,})/', $line, $m) === 1) {
                $fence = $m[1];
                $keep = $except instanceof Closure && $except($line);
                $buffer = [$line];

                continue;
            }

            if ($fence !== null) {
                $buffer[] = $line;

                $closer = '/^\s{0,3}' . $fence[0] . '{' . strlen($fence) . ',}\s*$/';

                if (preg_match($closer, $line) === 1) {
                    $block = implode("\n", $buffer);
                    $output[] = $keep ? $block : $token($block);
                    $fence = null;
                    $buffer = [];
                    $keep = false;
                }

                continue;
            }

            $output[] = self::maskInline($line, $token);
        }

        // An unterminated fence leaves the rest of the document untouched, just
        // as apply() never invokes its callback once inside an open fence.
        if ($fence !== null) {
            $block = implode("\n", $buffer);
            $output[] = $keep ? $block : $token($block);
        }

        $restore = static fn (string $text): string => strtr($text, $placeholders);

        return [implode("\n", $output), $restore];
    }

    /**
     * Replace inline code spans on a single line with placeholder tokens,
     * leaving the surrounding prose verbatim.
     *
     * @param  Closure(string): string  $token
     */
    private static function maskInline(string $line, Closure $token): string
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
                continue;
            }

            $result .= substr($line, $textStart, $runStart - $textStart);
            $result .= $token(substr($line, $runStart, ($close + $runLength) - $runStart));
            $i = $close + $runLength;
            $textStart = $i;
        }

        return $result . substr($line, $textStart);
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

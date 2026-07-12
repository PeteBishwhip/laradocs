<?php

declare(strict_types=1);

namespace Laradocs\Routing;

/**
 * Builds a robots.txt body from the package's config.
 *
 * When the master switch is off the entire site is disallowed and the sitemap
 * pointer is omitted — there is nothing useful for a crawler to do. Otherwise
 * the configured rule blocks are emitted (a permissive default when none are
 * given) followed by a Sitemap directive pointing at the package's sitemap.
 */
final class RobotsBuilder
{
    /**
     * @param  array<array-key, mixed>  $rules
     */
    public function build(bool $enabled, array $rules, ?string $sitemap = null): string
    {
        if (! $enabled) {
            return "User-agent: *\nDisallow: /\n";
        }

        $blocks = [];

        foreach ($this->normalise($rules) as $rule) {
            $lines = [];

            foreach ($rule['user_agents'] as $agent) {
                $lines[] = 'User-agent: ' . $agent;
            }

            foreach ($rule['disallow'] as $path) {
                $lines[] = 'Disallow: ' . $path;
            }

            foreach ($rule['allow'] as $path) {
                $lines[] = 'Allow: ' . $path;
            }

            if (count($lines) > count($rule['user_agents'])) {
                $blocks[] = implode("\n", $lines);
            }
        }

        if ($blocks === []) {
            $blocks[] = "User-agent: *\nAllow: /";
        }

        $body = implode("\n\n", $blocks);

        if ($sitemap !== null && $sitemap !== '') {
            $body .= "\n\nSitemap: " . $sitemap;
        }

        return $body . "\n";
    }

    /**
     * @param  array<array-key, mixed>  $rules
     * @return array<int, array{user_agents: array<int, string>, allow: array<int, string>, disallow: array<int, string>}>
     */
    private function normalise(array $rules): array
    {
        $normalised = [];

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $agents = $this->strings($rule['user_agent'] ?? $rule['user_agents'] ?? '*');

            if ($agents === []) {
                $agents = ['*'];
            }

            $normalised[] = [
                'user_agents' => $agents,
                'allow' => $this->strings($rule['allow'] ?? []),
                'disallow' => $this->strings($rule['disallow'] ?? []),
            ];
        }

        return $normalised;
    }

    /**
     * @return array<int, string>
     * @param mixed $value
     */
    private function strings($value): array
    {
        if (is_string($value)) {
            $value = [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        $out = [];

        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $out[] = $item;
            }
        }

        return $out;
    }
}

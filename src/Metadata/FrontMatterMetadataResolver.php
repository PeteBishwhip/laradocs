<?php

declare(strict_types=1);

namespace Laradocs\Metadata;

use Laradocs\Contracts\MetadataResolver;
use Symfony\Component\Yaml\Yaml;
use Throwable;

final class FrontMatterMetadataResolver implements MetadataResolver
{
    /**
     * @return array{0: array<string, mixed>, 1: string}
     */
    public function resolve(string $raw): array
    {
        try {
            if (! preg_match('/\A---\R(.*?)\R---(?:\R|\z)/s', $raw, $matches)) {
                return [[], $raw];
            }

            $matter = Yaml::parse($matches[1]);

            if (! is_array($matter)) {
                $matter = [];
            }

            return [$matter, (string) substr($raw, strlen($matches[0]))];
        } catch (Throwable $exception) {
            // Malformed front-matter: treat the whole file as body.
            return [[], $raw];
        }
    }
}

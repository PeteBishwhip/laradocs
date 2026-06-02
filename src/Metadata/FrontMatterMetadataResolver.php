<?php

declare(strict_types=1);

namespace Laradocs\Metadata;

use Laradocs\Contracts\MetadataResolver;
use Spatie\YamlFrontMatter\YamlFrontMatter;
use Throwable;

final class FrontMatterMetadataResolver implements MetadataResolver
{
    /**
     * @return array{0: array<string, mixed>, 1: string}
     */
    public function resolve(string $raw): array
    {
        try {
            $document = YamlFrontMatter::parse($raw);

            /** @var array<string, mixed> $matter */
            $matter = $document->matter();

            return [$matter, $document->body()];
        } catch (Throwable) {
            // Malformed front-matter: treat the whole file as body.
            return [[], $raw];
        }
    }
}

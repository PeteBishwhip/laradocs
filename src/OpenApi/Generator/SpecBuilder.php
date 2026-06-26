<?php

declare(strict_types=1);

namespace Laradocs\OpenApi\Generator;

use Illuminate\Filesystem\Filesystem;
use Laradocs\OpenApi\OpenApiParser;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * The document-level half of the Pillar B generator: it assembles the complete
 * OpenAPI 3.x document (delegating the route → operation assembly to
 * {@see SpecGenerator}, which applies {@see AttributeReader} overrides) and
 * serialises it to YAML, writing the artifact to the configured path.
 *
 * The emitted YAML is designed to round-trip: it parses cleanly back through
 * {@see OpenApiParser} and renders through the Pillar A
 * reference pages, so the generated spec is immediately usable, not just a dump.
 *
 * Serialising requires symfony/yaml; gate calls behind
 * `class_exists(\Symfony\Component\Yaml\Yaml::class)`.
 */
final class SpecBuilder
{
    public function __construct(
        private readonly SpecGenerator $generator,
        private readonly Filesystem $files = new Filesystem,
    ) {}

    /**
     * Assemble the OpenAPI document as a plain nested array.
     *
     * @return array<string, mixed>
     */
    public function build(): array
    {
        return $this->generator->generate();
    }

    /**
     * Serialise an assembled spec (or a freshly built one) to a YAML string.
     *
     * @param  array<string, mixed>|null  $spec
     */
    public function toYaml(?array $spec = null): string
    {
        // @codeCoverageIgnoreStart
        if (! class_exists(Yaml::class)) {
            throw new RuntimeException(
                'Dumping an OpenAPI spec to YAML requires the symfony/yaml package.',
            );
        }
        // @codeCoverageIgnoreEnd

        return Yaml::dump($spec ?? $this->build(), 8, 2, Yaml::DUMP_OBJECT_AS_MAP);
    }

    /**
     * Build (or accept) the spec, dump it to YAML, and write it to $path,
     * creating the parent directory if needed. Returns the YAML written.
     *
     * @param  array<string, mixed>|null  $spec
     */
    public function dump(string $path, ?array $spec = null): string
    {
        $yaml = $this->toYaml($spec ?? $this->build());

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $yaml);

        return $yaml;
    }
}

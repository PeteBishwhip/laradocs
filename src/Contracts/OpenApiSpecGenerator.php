<?php

declare(strict_types=1);

namespace Laradocs\Contracts;

use Laradocs\OpenApi\Generator\SpecBuilder;
use Laradocs\OpenApi\Generator\SpecGenerator;

/**
 * Contract for assembling an OpenAPI document as a plain nested array.
 *
 * The package ships {@see SpecGenerator} as the default implementation, which
 * reflects the application's route surface into a scaffold spec. Consumers such
 * as {@see SpecBuilder} depend on this contract rather than the concrete class,
 * so an alternative generator can be substituted without touching the builder.
 */
interface OpenApiSpecGenerator
{
    /**
     * Assemble the OpenAPI document as a plain nested array.
     *
     * @return array<string, mixed>
     */
    public function generate(): array;
}

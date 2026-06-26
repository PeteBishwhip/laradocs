<?php

declare(strict_types=1);

namespace Laradocs\OpenApi\Generator\Attributes;

use Attribute;
use Laradocs\OpenApi\Generator\AttributeReader;

/**
 * Explicit, developer-authored overrides for the operation generated from a
 * controller action by the `laradocs:openapi` command.
 *
 * Place it on the action method to override anything inference cannot recover or
 * gets wrong — a human-readable summary/description, a stable operationId, the
 * sidebar tags, or a deprecation flag:
 *
 *     #[ApiOperation(
 *         summary: 'List orders',
 *         description: 'Returns a paginated list of orders.',
 *         tags: ['Orders'],
 *     )]
 *     public function index(): OrderResourceCollection { ... }
 *
 * Every argument is optional; only the ones provided override the inferred
 * value. {@see AttributeReader} reads it.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class ApiOperation
{
    /**
     * @param  array<int, string>  $tags  Tags to file the operation under (replaces the inferred controller-derived tag).
     */
    public function __construct(
        public readonly ?string $summary = null,
        public readonly ?string $description = null,
        public readonly ?string $operationId = null,
        public readonly array $tags = [],
        public readonly bool $deprecated = false,
    ) {}
}

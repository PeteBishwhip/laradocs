<?php

declare(strict_types=1);

namespace Laradocs\OpenApi\Generator\Attributes;

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
final class ApiOperation
{
    /**
     * @readonly
     * @var string|null
     */
    public $summary;
    /**
     * @readonly
     * @var string|null
     */
    public $description;
    /**
     * @readonly
     * @var string|null
     */
    public $operationId;
    /**
     * @var array<int, string>
     * @readonly
     */
    public $tags = [];
    /**
     * @readonly
     * @var bool
     */
    public $deprecated = false;
    /**
     * @param  array<int, string>  $tags  Tags to file the operation under (replaces the inferred controller-derived tag).
     */
    public function __construct(?string $summary = null, ?string $description = null, ?string $operationId = null, array $tags = [], bool $deprecated = false)
    {
        $this->summary = $summary;
        $this->description = $description;
        $this->operationId = $operationId;
        $this->tags = $tags;
        $this->deprecated = $deprecated;
    }
}

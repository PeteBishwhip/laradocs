<?php

declare(strict_types=1);

namespace Laradocs\Tests\Fixtures\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Laradocs\OpenApi\Generator\Attributes\ApiOperation;

/**
 * A grab-bag controller whose actions exercise the degenerate inference paths of
 * the generator's request/response inspectors: FormRequests without/with broken
 * rules, non-resource and untyped return values, and an empty-bodied write.
 */
final class CoverageController extends Controller
{
    public function noRules(NoRulesRequest $request): OrderResource
    {
        return new OrderResource((object) []);
    }

    public function throwing(ThrowingRequest $request): OrderResource
    {
        return new OrderResource((object) []);
    }

    public function weird(WeirdRulesRequest $request): OrderResource
    {
        return new OrderResource((object) []);
    }

    public function returnsArray(): array
    {
        return [];
    }

    public function returnsJson(): JsonResponse
    {
        return new JsonResponse([]);
    }

    public function returnsBareCollection(): BareCollection
    {
        return new BareCollection([]);
    }

    public function noReturn()
    {
        // No return type-hint: the response inspector cannot infer a schema.
    }

    public function emptyPost(): OrderResource
    {
        return new OrderResource((object) []);
    }

    /**
     * Legacy lookup.
     *
     * Kept only for backwards compatibility.
     *
     * @deprecated
     */
    public function deprecatedDocblock(): OrderResource
    {
        return new OrderResource((object) []);
    }

    #[ApiOperation(description: 'Detailed.', operationId: 'customOpId')]
    public function fullyAttributed(): OrderResource
    {
        return new OrderResource((object) []);
    }
}

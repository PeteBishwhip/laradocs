<?php

declare(strict_types=1);

namespace Laradocs\Tests\Fixtures\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laradocs\OpenApi\Generator\Attributes\ApiOperation;

final class OrderController extends Controller
{
    #[ApiOperation(summary: 'List all orders', tags: ['Orders'], deprecated: true)]
    public function index(): OrderResourceCollection
    {
        return new OrderResourceCollection([]);
    }

    /**
     * Show a single order.
     *
     * Returns the order resource identified by the given id.
     */
    public function show(string $order): OrderResource
    {
        return new OrderResource((object) ['id' => $order]);
    }

    public function store(StoreOrderRequest $request): OrderResource
    {
        return new OrderResource((object) $request->validated());
    }

    public function search(Request $request): OrderResource
    {
        $request->validate([
            'term' => 'required|string|min:2',
            'limit' => 'integer|max:50',
        ]);

        return new OrderResource((object) []);
    }
}

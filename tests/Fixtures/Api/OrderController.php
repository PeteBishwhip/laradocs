<?php

declare(strict_types=1);

namespace Laradocs\Tests\Fixtures\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class OrderController extends Controller
{
    public function index(): OrderResourceCollection
    {
        return new OrderResourceCollection([]);
    }

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

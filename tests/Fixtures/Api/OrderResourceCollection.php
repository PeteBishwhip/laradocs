<?php

declare(strict_types=1);

namespace Laradocs\Tests\Fixtures\Api;

use Illuminate\Http\Resources\Json\ResourceCollection;

final class OrderResourceCollection extends ResourceCollection
{
    /**
     * @var class-string
     */
    public $collects = OrderResource::class;
}

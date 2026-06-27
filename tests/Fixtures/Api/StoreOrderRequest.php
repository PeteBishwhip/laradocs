<?php

declare(strict_types=1);

namespace Laradocs\Tests\Fixtures\Api;

use Illuminate\Foundation\Http\FormRequest;

final class StoreOrderRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reference' => 'required|string|max:32',
            'quantity' => 'required|integer|min:1|max:99',
            'email' => 'required|email',
            'status' => 'required|in:pending,paid,shipped',
            'notes' => 'nullable|string',
            'items' => 'array',
            'items.*.sku' => 'required|string',
        ];
    }
}

<?php

declare(strict_types=1);

namespace Laradocs\Tests\Fixtures\Api;

use Illuminate\Foundation\Http\FormRequest;
use RuntimeException;

/**
 * A FormRequest whose rules() throws — mimicking one that reads the route or
 * container at definition time. The RequestInspector must degrade to an empty
 * schema rather than letting the exception escape.
 */
final class ThrowingRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        throw new RuntimeException('rules() depends on runtime state');
    }
}

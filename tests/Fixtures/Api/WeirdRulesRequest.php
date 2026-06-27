<?php

declare(strict_types=1);

namespace Laradocs\Tests\Fixtures\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A FormRequest whose ruleset includes a value that is neither a string nor an
 * array, exercising the RuleMapper-feeding branch that skips unmappable rules.
 */
final class WeirdRulesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'count' => 123,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Laradocs\Tests\Fixtures\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A FormRequest that deliberately defines no rules() method, exercising the
 * RequestInspector branch that degrades to an empty schema.
 */
final class NoRulesRequest extends FormRequest {}

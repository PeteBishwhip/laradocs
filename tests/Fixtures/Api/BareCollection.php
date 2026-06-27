<?php

declare(strict_types=1);

namespace Laradocs\Tests\Fixtures\Api;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A ResourceCollection that leaves `$collects` at its default (null), so the
 * ResponseInspector cannot resolve the wrapped resource and falls back to an
 * untyped item schema.
 */
final class BareCollection extends ResourceCollection {}

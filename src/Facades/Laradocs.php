<?php

declare(strict_types=1);

namespace Laradocs\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;
use Laradocs\Documents\Document;
use Laradocs\Documents\DocumentCollection;
use Laradocs\Documents\DocumentTree;

/**
 * @method static \Laradocs\Laradocs variables(array<string, mixed>|Closure $values)
 * @method static \Laradocs\Laradocs share(string $key, mixed $value)
 * @method static \Laradocs\Laradocs macro(string $name, Closure|string $handler)
 * @method static DocumentCollection<int, Document> all()
 * @method static DocumentTree tree()
 * @method static Document|null find(string $slug)
 * @method static Document|null home()
 * @method static string render(Document $document)
 * @method static array<string, mixed> variableValues()
 *
 * @see \Laradocs\Laradocs
 */
final class Laradocs extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Laradocs\Laradocs::class;
    }
}

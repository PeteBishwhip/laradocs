<?php

declare(strict_types=1);

namespace Laradocs\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laradocs\Documents\Document;
use Laradocs\Laradocs;
use Laradocs\Routing\DocumentUrl;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

class ListPagesTool extends Tool
{
    /**
     * @readonly
     * @var \Laradocs\Laradocs
     */
    private $laradocs;
    /**
     * @var string
     */
    protected $name = 'list_pages';
    public function __construct(Laradocs $laradocs)
    {
        $this->laradocs = $laradocs;
    }
    public function handle(Request $request): Response
    {
        $group = $request->get('group');

        $pages = $this->laradocs->all()->visible()->ordered();

        if (is_string($group) && $group !== '') {
            $pages = $pages->filter(
                function (Document $doc) use ($group): bool {
                    return $doc->group() === $group;
                }
            )->values();
        }

        $mapped = $pages->map(function (Document $doc): array {
            return [
                'slug' => $doc->slug,
                'title' => $doc->title(),
                'group' => $doc->group(),
                'url' => DocumentUrl::toSlug($doc->slug),
            ];
        })->all();

        return Response::json(['pages' => $mapped]);
    }
    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'group' => $schema->string()
                ->description('Restrict the listing to pages within this group.'),
        ];
    }
}

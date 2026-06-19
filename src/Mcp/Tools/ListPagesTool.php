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

#[Description('List available documentation pages, optionally filtered to a single group.')]
class ListPagesTool extends Tool
{
    protected string $name = 'list_pages';

    public function __construct(private readonly Laradocs $laradocs) {}

    public function handle(Request $request): Response
    {
        $group = $request->get('group');

        $pages = $this->laradocs->all()->visible()->ordered();

        if (is_string($group) && $group !== '') {
            $pages = $pages->filter(
                fn (Document $doc): bool => $doc->group() === $group
            )->values();
        }

        $mapped = $pages->map(fn (Document $doc): array => [
            'slug' => $doc->slug,
            'title' => $doc->title(),
            'group' => $doc->group(),
            'url' => DocumentUrl::toSlug($doc->slug),
        ])->all();

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

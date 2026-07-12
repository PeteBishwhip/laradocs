<?php

declare(strict_types=1);

namespace Laradocs\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laradocs\Laradocs;
use Laradocs\Routing\DocumentUrl;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

class FetchPageTool extends Tool
{
    /**
     * @readonly
     * @var \Laradocs\Laradocs
     */
    private $laradocs;
    /**
     * @var string
     */
    protected $name = 'fetch_page';
    public function __construct(Laradocs $laradocs)
    {
        $this->laradocs = $laradocs;
    }
    public function handle(Request $request): Response
    {
        $request->validate(['slug' => 'required|string']);

        $slug = $request->string('slug')->toString();
        $document = $this->laradocs->all()->findBySlug($slug);

        if ($document === null) {
            return Response::error('Page not found: ' . $slug);
        }

        $metadata = $document->metadata;

        return Response::json([
            'slug' => $document->slug,
            'title' => $document->title(),
            'group' => $document->group(),
            'url' => DocumentUrl::toSlug($document->slug),
            'markdown' => $document->markdown,
            'metadata' => [
                'description' => $metadata->description,
                'author' => $metadata->author,
                'updated_at' => $metadata->updatedAt,
                'tags' => $metadata->tags,
                'hidden' => $metadata->hidden,
                'order' => $metadata->order === PHP_INT_MAX ? null : $metadata->order,
            ],
        ]);
    }
    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'slug' => $schema->string()
                ->description('The slug of the page to fetch.')
                ->required(),
        ];
    }
}

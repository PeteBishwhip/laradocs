<?php

declare(strict_types=1);

namespace Laradocs\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laradocs\Laradocs;
use Laradocs\Routing\DocumentUrl;
use Laradocs\Search\Contracts\SearchEngine;
use Laradocs\Search\Excerpt;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

class SearchDocsTool extends Tool
{
    /**
     * @readonly
     * @var \Laradocs\Laradocs
     */
    private $laradocs;
    /**
     * @readonly
     * @var \Laradocs\Search\Contracts\SearchEngine
     */
    private $engine;
    /**
     * @var string
     */
    protected $name = 'search_docs';
    public function __construct(Laradocs $laradocs, SearchEngine $engine)
    {
        $this->laradocs = $laradocs;
        $this->engine = $engine;
    }
    public function handle(Request $request): Response
    {
        $request->validate([
            'query' => 'required|string',
            'limit' => 'integer|min:1|max:100',
        ]);

        $query = $request->string('query')->toString();
        $limit = $request->integer('limit', 10);

        $results = $this->engine->search($query, $this->laradocs->searchIndex(), $limit);

        $mapped = array_map(function (array $entry) use ($query): array {
            return [
                'slug' => $entry['slug'],
                'title' => $entry['title'],
                'group' => $entry['group'],
                'url' => DocumentUrl::toSlug($entry['slug']),
                'excerpt' => Excerpt::make($entry['content'], $query),
            ];
        }, $results);

        return Response::json(['results' => $mapped]);
    }
    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('The search query.')
                ->required(),
            'limit' => $schema->integer()
                ->description('Maximum number of results to return.')
                ->default(10),
        ];
    }
}

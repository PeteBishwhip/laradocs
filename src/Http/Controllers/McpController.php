<?php

declare(strict_types=1);

namespace Laradocs\Http\Controllers;

use Composer\InstalledVersions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laradocs\Documents\Document;
use Laradocs\Laradocs;
use Laradocs\Routing\DocumentUrl;
use Laradocs\Search\Contracts\SearchEngine;
use Laradocs\Search\Excerpt;

/**
 * MCP (Model Context Protocol) endpoint.
 *
 * Speaks JSON-RPC 2.0 over a single POST. This class owns the protocol
 * framing — parsing, batch/notification handling, method dispatch and error
 * codes — and the capability-negotiation methods (`initialize`, `ping`). The
 * `tools/*` methods are dispatched here and fleshed out in later stories.
 */
final class McpController
{
    private const PROTOCOL_VERSION = '2025-03-26';

    public function __construct(
        private readonly Laradocs $laradocs,
        private readonly SearchEngine $engine,
    ) {}

    public function __invoke(Request $request): Response|JsonResponse
    {
        try {
            /** @var mixed $body */
            $body = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->jsonRpcError(null, -32700, 'Parse error');
        }

        if (! is_array($body)) {
            return $this->jsonRpcError(null, -32600, 'Invalid Request');
        }

        if (array_is_list($body)) {
            return $this->jsonRpcError(null, -32600, 'Batch requests not supported');
        }

        // Notifications carry no `id` and expect no response body.
        if (! array_key_exists('id', $body)) {
            return new Response('', 202);
        }

        /** @var mixed $id */
        $id = $body['id'];
        $method = is_string($body['method'] ?? null) ? $body['method'] : '';

        return match ($method) {
            'initialize' => $this->handleInitialize($id),
            'tools/list' => $this->handleToolsList($id),
            'tools/call' => $this->handleToolsCall($id, $body),
            'ping' => $this->success($id, new \stdClass),
            default => $this->jsonRpcError($id, -32601, 'Method not found'),
        };
    }

    private function handleInitialize(mixed $id): JsonResponse
    {
        return $this->success($id, [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => ['tools' => new \stdClass],
            'serverInfo' => [
                'name' => 'laradocs',
                'version' => $this->serverVersion(),
            ],
        ]);
    }

    private function handleToolsList(mixed $id): JsonResponse
    {
        return $this->success($id, ['tools' => $this->toolDefinitions()]);
    }

    /**
     * The full MCP tool catalogue advertised to clients, each entry carrying a
     * JSON Schema describing its parameters.
     *
     * @return list<array<string, mixed>>
     */
    private function toolDefinitions(): array
    {
        return [
            [
                'name' => 'search_docs',
                'description' => 'Full-text search across the documentation. Returns matching pages ranked by relevance.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'The search query.',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum number of results to return.',
                            'default' => 10,
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'list_pages',
                'description' => 'List the available documentation pages, optionally filtered to a single group.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'group' => [
                            'type' => 'string',
                            'description' => 'Restrict the listing to pages within this group.',
                        ],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'fetch_page',
                'description' => 'Fetch the full rendered content of a single documentation page by slug.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => [
                            'type' => 'string',
                            'description' => 'The slug of the page to fetch.',
                        ],
                    ],
                    'required' => ['slug'],
                ],
            ],
        ];
    }

    /**
     * @param  array<mixed>  $body
     */
    private function handleToolsCall(mixed $id, array $body): JsonResponse
    {
        $params = is_array($body['params'] ?? null) ? $body['params'] : [];

        // A missing or non-string tool name is a malformed request, not a tool
        // failure — surface it as a JSON-RPC protocol error.
        if (! is_string($params['name'] ?? null)) {
            return $this->jsonRpcError($id, -32602, 'Invalid params: tool name is required');
        }

        $name = $params['name'];
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        return match ($name) {
            'search_docs' => $this->callSearchDocs($id, $arguments),
            'list_pages' => $this->callListPages($id, $arguments),
            'fetch_page' => $this->callFetchPage($id, $arguments),
            default => $this->toolError($id, "Unknown tool: {$name}"),
        };
    }

    /**
     * The `search_docs` tool: full-text search over the index, returning the
     * matching pages with a short excerpt for each.
     *
     * @param  array<mixed>  $arguments
     */
    private function callSearchDocs(mixed $id, array $arguments): JsonResponse
    {
        if (! is_string($arguments['query'] ?? null)) {
            return $this->toolError($id, 'The "query" argument is required and must be a string.');
        }

        $query = $arguments['query'];
        $limitRaw = $arguments['limit'] ?? 10;
        $limit = is_numeric($limitRaw) ? (int) $limitRaw : 10;
        $limit = max(1, min(100, $limit));

        $results = $this->engine->search($query, $this->laradocs->searchIndex(), $limit);

        $mapped = array_map(fn (array $entry): array => [
            'slug' => $entry['slug'],
            'title' => $entry['title'],
            'group' => $entry['group'],
            'url' => DocumentUrl::toSlug($entry['slug']),
            'excerpt' => Excerpt::make($entry['content'], $query),
        ], $results);

        return $this->toolContent($id, ['results' => $mapped]);
    }

    /**
     * The `list_pages` tool: enumerate every visible page, optionally narrowed
     * to a single group, without triggering the HTML render pipeline.
     *
     * @param  array<mixed>  $arguments
     */
    private function callListPages(mixed $id, array $arguments): JsonResponse
    {
        $group = is_string($arguments['group'] ?? null) ? $arguments['group'] : null;

        $pages = $this->laradocs->all()->visible()->ordered();

        if ($group !== null) {
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

        return $this->toolContent($id, ['pages' => $mapped]);
    }

    /**
     * The `fetch_page` tool: return the raw markdown and front-matter metadata
     * for a single page, looked up by slug. Deliberately avoids
     * `Laradocs::find()`, which would render (and cache) the page's HTML.
     *
     * @param  array<mixed>  $arguments
     */
    private function callFetchPage(mixed $id, array $arguments): JsonResponse
    {
        if (! is_string($arguments['slug'] ?? null)) {
            return $this->toolError($id, 'The "slug" argument is required and must be a string.');
        }

        $slug = $arguments['slug'];
        $document = $this->laradocs->all()->findBySlug($slug);

        if ($document === null) {
            return $this->toolError($id, 'Page not found: ' . $slug);
        }

        $metadata = $document->metadata;

        return $this->toolContent($id, [
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

    private function serverVersion(): string
    {
        try {
            return InstalledVersions::getVersion('petebishwhip/laradocs') ?? 'dev';
        } catch (\OutOfBoundsException) {
            return 'dev';
        }
    }

    /**
     * A successful JSON-RPC 2.0 response envelope.
     *
     * @param  array<string, mixed>|object  $result
     */
    private function success(mixed $id, array|object $result): JsonResponse
    {
        return $this->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ]);
    }

    /**
     * A successful `tools/call` result carrying text content.
     *
     * @param  array<string, mixed>  $data
     */
    private function toolContent(mixed $id, array $data): JsonResponse
    {
        return $this->success($id, [
            'content' => [
                [
                    'type' => 'text',
                    'text' => (string) json_encode($data, JSON_THROW_ON_ERROR),
                ],
            ],
            'isError' => false,
        ]);
    }

    /**
     * A `tools/call` result flagged as an error (the call succeeded at the
     * protocol level but the tool itself failed).
     */
    private function toolError(mixed $id, string $message): JsonResponse
    {
        return $this->success($id, [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $message,
                ],
            ],
            'isError' => true,
        ]);
    }

    /**
     * A JSON-RPC 2.0 error response envelope.
     */
    private function jsonRpcError(mixed $id, int $code, string $message): JsonResponse
    {
        return $this->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function json(array $payload): JsonResponse
    {
        return (new JsonResponse($payload))->header('Content-Type', 'application/json');
    }
}

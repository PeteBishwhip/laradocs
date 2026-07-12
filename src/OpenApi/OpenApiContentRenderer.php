<?php

declare(strict_types=1);

namespace Laradocs\OpenApi;

use Closure;
use Laradocs\Contracts\DocumentContentRenderer;
use Laradocs\Contracts\DocumentParser;
use Laradocs\Documents\Document;
use Laradocs\Support\Config;

/**
 * Renders an OpenAPI-backed synthetic {@see Document} to native, themed HTML.
 *
 * The loader (US-003) emits one synthetic document per operation plus an
 * overview document, each tagged with an `extra['openapi']` marker. This
 * renderer is the {@see DocumentContentRenderer} that recognises that marker
 * and produces the page body: it re-parses the cached {@see NormalizedSpec},
 * locates the operation (or overview), expands request/response schemas via
 * {@see SchemaRenderer} and hands the result to the Blade partials under
 * `resources/views/partials/openapi`.
 *
 * Spec `description` fields are run through the site's {@see DocumentParser}
 * (the same markdown pipeline the rest of the docs use) when
 * `render_markdown_descriptions` is on, so API copy matches site markdown;
 * otherwise they are emitted as escaped plain text.
 */
final class OpenApiContentRenderer implements DocumentContentRenderer
{
    /**
     * @readonly
     * @var \Laradocs\OpenApi\OpenApiParser
     */
    private $parser;
    /**
     * @readonly
     * @var \Laradocs\Contracts\DocumentParser
     */
    private $markdown;
    /**
     * @readonly
     * @var bool
     */
    private $renderMarkdownDescriptions = true;
    public function __construct(OpenApiParser $parser, DocumentParser $markdown, bool $renderMarkdownDescriptions = true)
    {
        $this->parser = $parser;
        $this->markdown = $markdown;
        $this->renderMarkdownDescriptions = $renderMarkdownDescriptions;
    }

    public function supports(Document $document): bool
    {
        $marker = $document->metadata->get('openapi');

        return is_array($marker) && $marker !== [];
    }

    public function render(Document $document): string
    {
        $marker = $document->metadata->get('openapi');

        if (! is_array($marker)) {
            return '';
        }

        return $this->renderMarker(Coerce::assoc($marker));
    }

    /**
     * @param  array<string, mixed>  $marker
     */
    private function renderMarker(array $marker): string
    {
        $specPath = Coerce::string($marker['spec'] ?? '');

        if ($specPath === '' || ! is_file($specPath)) {
            return '';
        }

        $spec = $this->parser->parse($specPath);
        $schema = new SchemaRenderer($spec);
        $describe = $this->describer();

        if (Coerce::string($marker['type'] ?? '') === 'operation') {
            return $this->renderOperation($spec, $schema, $describe, Coerce::assoc($marker['op'] ?? []));
        }

        // Operation links resolve against the canonical (default-locale) spec so a
        // translated overview never points at translated slugs the loader didn't
        // mount. Fall back to the active spec when no distinct canonical exists.
        $canonicalPath = Coerce::string($marker['canonicalSpec'] ?? '');
        $canonicalSpec = $canonicalPath !== '' && $canonicalPath !== $specPath && is_file($canonicalPath)
            ? $this->parser->parse($canonicalPath)
            : $spec;

        return $this->renderOverview($spec, $canonicalSpec, $describe);
    }

    /**
     * A closure that turns a spec `description` into HTML: through the markdown
     * pipeline when enabled, otherwise as an escaped paragraph.
     *
     * @return Closure(?string): string
     */
    private function describer(): Closure
    {
        return function (?string $text): string {
            $text = $text ?? '';

            if (trim($text) === '') {
                return '';
            }

            return $this->renderMarkdownDescriptions
                ? $this->markdown->parse($text)
                : '<p>' . e($text) . '</p>';
        };
    }

    /**
     * @param  Closure(?string): string  $describe
     * @param  array<string, mixed>  $op
     */
    private function renderOperation(NormalizedSpec $spec, SchemaRenderer $schema, Closure $describe, array $op): string
    {
        $method = strtoupper(Coerce::string($op['method'] ?? ''));
        $path = Coerce::string($op['path'] ?? '');
        $operation = $this->findOperation($spec, $method, $path);

        if ($operation === null) {
            return '';
        }

        $servers = $spec->servers();
        $baseUrl = isset($servers[0]['url']) && is_scalar($servers[0]['url'])
            ? rtrim((string) $servers[0]['url'], '/')
            : '';

        $requestBody = $this->expandRequestBody($operation->requestBody, $schema);
        $responses = $this->expandResponses($operation->responses, $schema);

        return (string) view('laradocs::partials.openapi.operation', [
            'operation' => $operation,
            'baseUrl' => $baseUrl,
            'parameters' => $this->expandParameters($operation->parameters, $schema),
            'requestBody' => $requestBody,
            'responses' => $responses,
            'samples' => $this->samples($operation, $baseUrl, $requestBody, $responses),
            'describe' => $describe,
        ])->render();
    }

    /**
     * Build the request/response code-sample panel for the operation sidebar:
     * cURL/PHP/JS/Python/Ruby request snippets (as a highlighted, tabbed code
     * group via the site's own markdown pipeline) plus an example JSON response.
     *
     * @param  array<string, mixed>|null  $requestBody
     * @param  array<int, array<string, mixed>>  $responses
     * @return array{request: array<string, string>, response: ?string, method: string, status: ?string}
     */
    private function samples(Operation $operation, string $baseUrl, ?array $requestBody, array $responses): array
    {
        $builder = new CodeSampleBuilder;
        $url = ($baseUrl === '' ? '' : $baseUrl) . $operation->path;

        $requestSchema = $requestBody === null
            ? null
            : $this->firstSchema(Coerce::listOfAssoc($requestBody['content'] ?? []));
        $languages = $builder->forOperation($operation->method, $url, $requestSchema);

        // One highlighted code block per language, keyed by label. The view wraps
        // them in a dropdown-driven switcher rather than tabs (which overflow the
        // narrow rail), and JS persists the chosen language across pages.
        $fences = ['cURL' => 'bash', 'PHP' => 'php', 'JavaScript' => 'javascript', 'Python' => 'python', 'Ruby' => 'ruby'];
        $request = [];
        foreach ($languages as $label => $code) {
            $request[$label] = $this->markdown->parse("```{$fences[$label]}\n{$code}\n```");
        }

        [$responseSchema, $status] = $this->firstSuccessResponse($responses);
        $responseJson = $builder->responseJson($responseSchema);

        return [
            'request' => $request,
            'response' => $responseJson === null ? null : $this->markdown->parse("```json\n{$responseJson}\n```"),
            'method' => $operation->method,
            'status' => $status,
        ];
    }

    /**
     * The first resolved schema node from a content map (media type => media).
     *
     * @param  array<int, array<string, mixed>>  $content
     * @return array<string, mixed>|null
     */
    private function firstSchema(array $content): ?array
    {
        foreach ($content as $media) {
            if (is_array($media['schema'] ?? null)) {
                return Coerce::assoc($media['schema']);
            }
        }

        return null;
    }

    /**
     * The schema and status code of the first 2xx response carrying a body,
     * used to render an example response payload.
     *
     * @param  array<int, array<string, mixed>>  $responses
     * @return array{0: array<string, mixed>|null, 1: ?string}
     */
    private function firstSuccessResponse(array $responses): array
    {
        foreach ($responses as $response) {
            $status = Coerce::string($response['status'] ?? '');
            if (strncmp($status, '2', strlen('2')) === 0 && ($schema = $this->firstSchema(Coerce::listOfAssoc($response['content'] ?? []))) !== null) {
                return [$schema, $status];
            }
        }

        return [null, null];
    }

    /**
     * @param  Closure(?string): string  $describe
     */
    private function renderOverview(NormalizedSpec $spec, NormalizedSpec $canonicalSpec, Closure $describe): string
    {
        $info = $spec->info();
        $baseSlug = Config::string('laradocs.openapi.base_slug', 'api');

        return (string) view('laradocs::partials.openapi.overview', [
            'info' => $info,
            'infoDescription' => Coerce::nullableString($info['description'] ?? null),
            'servers' => $spec->servers(),
            'tags' => $spec->tags(),
            'operations' => $spec->operations(),
            // Resolve slugs the same way the loader mounts the pages — against the
            // canonical spec — so the index links always point at the real
            // operation URLs, even when this locale's summaries are translated.
            'operationSlugs' => OperationSlugger::resolve($spec->operations(), $canonicalSpec->operations(), $baseSlug),
            'describe' => $describe,
        ])->render();
    }

    private function findOperation(NormalizedSpec $spec, string $method, string $path): ?Operation
    {
        foreach ($spec->operations() as $operation) {
            if ($operation->method === $method && $operation->path === $path) {
                return $operation;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $parameters
     * @return array<int, array<string, mixed>>
     */
    private function expandParameters(array $parameters, SchemaRenderer $schema): array
    {
        $out = [];

        foreach ($parameters as $parameter) {
            $definition = Coerce::assoc($parameter['schema'] ?? []);

            $out[] = [
                'name' => Coerce::string($parameter['name'] ?? ''),
                'in' => Coerce::string($parameter['in'] ?? ''),
                'required' => Coerce::bool($parameter['required'] ?? false),
                'deprecated' => Coerce::bool($parameter['deprecated'] ?? false),
                'description' => Coerce::nullableString($parameter['description'] ?? null),
                'schema' => $definition === [] ? null : $schema->render($definition),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $requestBody
     * @return array<string, mixed>|null
     */
    private function expandRequestBody(array $requestBody, SchemaRenderer $schema): ?array
    {
        if ($requestBody === []) {
            return null;
        }

        return [
            'description' => Coerce::nullableString($requestBody['description'] ?? null),
            'required' => Coerce::bool($requestBody['required'] ?? false),
            'content' => $this->expandContent(Coerce::assoc($requestBody['content'] ?? []), $schema),
        ];
    }

    /**
     * @param  array<string, mixed>  $responses
     * @return array<int, array<string, mixed>>
     */
    private function expandResponses(array $responses, SchemaRenderer $schema): array
    {
        $out = [];

        foreach ($responses as $status => $response) {
            $response = Coerce::assoc($response);

            $out[] = [
                'status' => (string) $status,
                'description' => Coerce::nullableString($response['description'] ?? null),
                'content' => $this->expandContent(Coerce::assoc($response['content'] ?? []), $schema),
            ];
        }

        return $out;
    }

    /**
     * Expand a `content` map (media type => media object) into a list of media
     * types each carrying its fully-resolved schema node tree.
     *
     * @param  array<string, mixed>  $content
     * @return array<int, array<string, mixed>>
     */
    private function expandContent(array $content, SchemaRenderer $schema): array
    {
        $out = [];

        foreach ($content as $mediaType => $media) {
            $media = Coerce::assoc($media);
            $definition = Coerce::assoc($media['schema'] ?? []);

            $out[] = [
                'mediaType' => (string) $mediaType,
                'schema' => $definition === [] ? null : $schema->render($definition),
            ];
        }

        return $out;
    }
}

<?php

declare(strict_types=1);

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Laradocs\Contracts\DocumentLoader;
use Laradocs\Documents\Document;
use Laradocs\Documents\DocumentCollection;
use Laradocs\Documents\DocumentTree;
use Laradocs\Loaders\CompositeDocumentLoader;
use Laradocs\Loaders\OpenApiLoader;
use Laradocs\Metadata\Metadata;
use Laradocs\OpenApi\OpenApiParser;

$fixtures = dirname(__DIR__) . '/Fixtures/openapi';

function makeOpenApiLoader(string $dir, string $locale = ''): OpenApiLoader
{
    /** @var Repository $store */
    $store = Cache::store();

    return new OpenApiLoader(
        new OpenApiParser($store, cacheEnabled: false),
        $dir,
        ['petstore-3.0.yaml'],
        'api',
        null,
        'API Reference',
        100,
        $locale,
    );
}

it('emits one overview document plus one document per operation', function () use ($fixtures) {
    $documents = makeOpenApiLoader($fixtures)->all();

    expect($documents)->toHaveCount(3); // overview + 2 operations

    $overview = $documents->findBySlug('api');
    expect($overview)->toBeInstanceOf(Document::class)
        ->and($overview->markdown)->toBe('')
        ->and($overview->metadata->get('openapi'))->toMatchArray([
            'type' => 'overview',
        ]);

    $slugs = $documents->map(fn (Document $doc): string => $doc->slug)->all();
    expect($slugs)->toContain('api')
        ->toContain('api/pets/list-all-pets')
        ->toContain('api/pets/create-a-pet');
});

it('marks each operation document with its operation reference', function () use ($fixtures) {
    $documents = makeOpenApiLoader($fixtures)->all();

    $list = $documents->findBySlug('api/pets/list-all-pets');
    expect($list)->toBeInstanceOf(Document::class)
        ->and($list->title())->toBe('List all pets')
        ->and($list->group())->toBe('pets')
        ->and($list->metadata->tags)->toBe(['pets']);

    /** @var array<string, mixed> $marker */
    $marker = $list->metadata->get('openapi');
    expect($marker['type'])->toBe('operation')
        ->and($marker['op']['method'])->toBe('GET')
        ->and($marker['op']['path'])->toBe('/pets')
        ->and($marker['op']['operationId'])->toBe('listPets');
});

it('gives every synthetic document a distinct path and relativePath', function () use ($fixtures) {
    $documents = makeOpenApiLoader($fixtures)->all();

    $paths = $documents->map(fn (Document $doc): string => $doc->path)->all();
    $relatives = $documents->map(fn (Document $doc): string => $doc->relativePath)->all();

    expect($paths)->toHaveCount(3)
        ->and(array_unique($paths))->toHaveCount(3)
        ->and($relatives)->toEqual($paths);

    // Each reference embeds the spec file and the operation key.
    $overview = $documents->findBySlug('api');
    expect($overview->path)->toContain('petstore-3.0.yaml#overview');
});

it('embeds the active locale in the document path so locale variants do not collide', function () use ($fixtures) {
    $en = makeOpenApiLoader($fixtures, 'en')->all()->findBySlug('api/pets/list-all-pets');
    $fr = makeOpenApiLoader($fixtures, 'fr')->all()->findBySlug('api/pets/list-all-pets');

    expect($en->path)->toContain('@en')
        ->and($fr->path)->toContain('@fr')
        ->and($en->path)->not->toBe($fr->path)
        ->and($en->locale)->toBe('en')
        ->and($fr->locale)->toBe('fr');

    // Same slug, different cache identity — the HTML cache key folds in $path.
    expect($en->slug)->toBe($fr->slug);
});

it('groups operations by first tag and nests them under the base slug in the tree', function () use ($fixtures) {
    $documents = makeOpenApiLoader($fixtures)->all();

    $tree = DocumentTree::fromDocuments($documents);

    // The base slug is a single root carrying the overview document.
    $roots = array_values(array_filter(
        $tree->roots,
        fn ($node): bool => $node->slug === 'api',
    ));
    expect($roots)->toHaveCount(1);

    $api = $roots[0];
    expect($api->document?->slug)->toBe('api');

    // Operations nest one level deeper, under their first tag ("pets").
    $tag = collect($api->children)->firstWhere('slug', 'api/pets');
    expect($tag)->not->toBeNull();

    $opSlugs = collect($tag->children)->map(fn ($node): string => $node->slug)->all();
    expect($opSlugs)->toContain('api/pets/list-all-pets')
        ->toContain('api/pets/create-a-pet');
});

it('resolves filesystem documents before OpenApi documents on a slug collision', function () use ($fixtures) {
    $userDoc = new Document(
        path: '/docs/api.md',
        relativePath: 'api.md',
        slug: 'api',
        metadata: Metadata::fromArray(['title' => 'Hand-written API page']),
        markdown: '# API',
    );

    $filesystem = new class($userDoc) implements DocumentLoader
    {
        public function __construct(private readonly Document $doc) {}

        public function all(): DocumentCollection
        {
            return new DocumentCollection([$this->doc]);
        }

        public function find(string $slug): ?Document
        {
            return $slug === $this->doc->slug ? $this->doc : null;
        }
    };

    $composite = new CompositeDocumentLoader([$filesystem, makeOpenApiLoader($fixtures)]);

    // Filesystem wins the "api" slug collision...
    $resolved = $composite->find('api');
    expect($resolved->title())->toBe('Hand-written API page')
        ->and($resolved->markdown)->toBe('# API');

    // ...while OpenApi-only slugs still resolve through the composite.
    expect($composite->find('api/pets/list-all-pets'))->toBeInstanceOf(Document::class);

    // all() merges both loaders' documents.
    expect($composite->all()->count())->toBe(1 + 3);
});

it('yields no documents when the docs directory does not exist', function () {
    expect(makeOpenApiLoader('/no/such/laradocs/dir')->all())->toHaveCount(0);
});

it('falls back to method and path when an operation has no summary or operationId', function () {
    $dir = sys_get_temp_dir() . '/laradocs-noopid-' . bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);

    // JSON is valid YAML, so this is parsed by the .yaml reader. No summary and
    // no operationId, so the slug segment falls through to the method + path.
    file_put_contents($dir . '/petstore-3.0.yaml', (string) json_encode([
        'openapi' => '3.0.3',
        'info' => ['title' => 'T', 'version' => '1.0.0'],
        'paths' => [
            '/things' => [
                'get' => [
                    'responses' => ['200' => ['description' => 'ok']],
                ],
            ],
        ],
    ]));

    $documents = makeOpenApiLoader($dir)->all();

    expect($documents->findBySlug('api/default/get-things'))->not->toBeNull();

    unlink($dir . '/petstore-3.0.yaml');
    rmdir($dir);
});

it('returns null from composite find when no loader resolves the slug', function () use ($fixtures) {
    $composite = new CompositeDocumentLoader([makeOpenApiLoader($fixtures)]);

    expect($composite->find('does/not/exist'))->toBeNull();
});

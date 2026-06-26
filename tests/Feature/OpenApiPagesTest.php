<?php

declare(strict_types=1);

use cebe\openapi\Reader;
use Laradocs\Documents\TreeNode;
use Laradocs\Laradocs;

/**
 * End-to-end coverage for the OpenAPI reference pages (Pillar A). Each test
 * drops a spec into the docs path via {@see makeDocs()} and exercises the full
 * pipeline over HTTP — sidebar tree, operation rendering, schema expansion,
 * search, sitemap, SEO, caching and per-locale isolation.
 *
 * Every acceptance test runs against both a 3.0 and a 3.1 spec through the same
 * assertions (the two only differ in how a nullable field is expressed), so the
 * normalisation seam is verified across both spec versions.
 */
beforeEach(function () {
    if (! class_exists(Reader::class)) {
        $this->markTestSkipped('devizzent/cebe-php-openapi is not installed.');
    }

    config()->set('laradocs.openapi.enabled', true);
    config()->set('laradocs.ui.brand.title', 'Acme Docs');
});

/**
 * The shared fixture spec. The two versions describe an identical API surface;
 * only the encoding of the nullable `notes` field differs (3.0 `nullable: true`
 * vs 3.1 `type: [string, "null"]`), so a single set of assertions applies to
 * both once the parser/renderer have normalised them.
 *
 * @return array<string, mixed>
 */
function widgetsSpec(string $version): array
{
    $nullableString = str_starts_with($version, '3.1')
        ? ['type' => ['string', 'null']]
        : ['type' => 'string', 'nullable' => true];

    return [
        'openapi' => $version,
        'info' => [
            'title' => 'Widgets API',
            'version' => '1.0.0',
            'description' => 'End-to-end fixture API.',
        ],
        'servers' => [
            ['url' => 'https://api.example.com/v1', 'description' => 'Production'],
        ],
        'tags' => [
            ['name' => 'widgets', 'description' => 'Widget operations'],
            ['name' => 'orders', 'description' => 'Order operations'],
        ],
        'paths' => [
            '/widgets' => [
                'get' => [
                    'operationId' => 'listWidgets',
                    'summary' => 'List all widgets',
                    'description' => 'Returns a paginated list of widgets.',
                    'tags' => ['widgets'],
                    'parameters' => [
                        [
                            'name' => 'status',
                            'in' => 'query',
                            'required' => false,
                            'schema' => ['type' => 'string', 'enum' => ['active', 'archived']],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'A list of widgets.',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'array',
                                        'items' => ['$ref' => '#/components/schemas/Widget'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'post' => [
                    'operationId' => 'createWidget',
                    'summary' => 'Create a widget',
                    'description' => 'Adds a new widget to the catalogue.',
                    'tags' => ['widgets'],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/WidgetInput'],
                            ],
                        ],
                    ],
                    'responses' => [
                        '201' => [
                            'description' => 'The created widget.',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/Widget'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/orders/{orderId}' => [
                'get' => [
                    'operationId' => 'getOrder',
                    'summary' => 'Fetch an order',
                    'description' => 'Returns a single order by id.',
                    'tags' => ['orders'],
                    'parameters' => [
                        [
                            'name' => 'orderId',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'string'],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'The order.',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/Order'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'components' => [
            'schemas' => [
                // allOf composed from a $ref + an inline object, so both the
                // expand-$ref and merge-allOf paths are exercised at once.
                'Widget' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/Identifiable'],
                        [
                            'type' => 'object',
                            'required' => ['name'],
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'kind' => ['type' => 'string', 'enum' => ['gadget', 'gizmo']],
                                'notes' => $nullableString,
                            ],
                        ],
                    ],
                ],
                'Identifiable' => [
                    'type' => 'object',
                    'required' => ['id'],
                    'properties' => [
                        'id' => ['type' => 'integer', 'format' => 'int64'],
                    ],
                ],
                'WidgetInput' => [
                    'type' => 'object',
                    'required' => ['name'],
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'payment' => [
                            'oneOf' => [
                                ['$ref' => '#/components/schemas/CardPayment'],
                                ['$ref' => '#/components/schemas/CashPayment'],
                            ],
                        ],
                    ],
                ],
                'CardPayment' => [
                    'type' => 'object',
                    'properties' => ['cardNumber' => ['type' => 'string']],
                ],
                'CashPayment' => [
                    'type' => 'object',
                    'properties' => ['amount' => ['type' => 'number']],
                ],
                'Order' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'source' => [
                            'anyOf' => [
                                ['type' => 'string'],
                                ['type' => 'integer'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

/**
 * The fixture spec plus one extra operation, used to assert that touching the
 * spec (new mtime) rebuilds the tree/search/sitemap caches.
 *
 * @return array<string, mixed>
 */
function widgetsSpecWithExtraOp(string $version): array
{
    $spec = widgetsSpec($version);
    $spec['tags'][] = ['name' => 'audits', 'description' => 'Audit log'];
    $spec['paths']['/audits'] = [
        'get' => [
            'operationId' => 'listAudits',
            'summary' => 'List audits',
            'description' => 'Returns the audit log.',
            'tags' => ['audits'],
            'responses' => [
                '200' => ['description' => 'Audit entries.'],
            ],
        ],
    ];

    return $spec;
}

/**
 * Encode a fixture spec as JSON for writing to `openapi.json` in the docs path.
 */
function widgetsSpecJson(string $version, bool $extra = false): string
{
    $spec = $extra ? widgetsSpecWithExtraOp($version) : widgetsSpec($version);

    return (string) json_encode($spec, JSON_PRETTY_PRINT);
}

dataset('openapi specs', [
    '3.0' => ['3.0.3'],
    '3.1' => ['3.1.0'],
]);

it('groups operations under their tag in the sidebar tree', function (string $version) {
    $this->makeDocs(['openapi.json' => widgetsSpecJson($version)]);

    // Resolve the page so the tree is built, then inspect it structurally.
    $this->get('/docs/api')->assertOk();

    $nav = app(Laradocs::class)->tree()->navigation();

    $find = function (array $nodes, string $slug) use (&$find): ?TreeNode {
        foreach ($nodes as $node) {
            if ($node->slug === $slug) {
                return $node;
            }

            if (($hit = $find($node->children, $slug)) !== null) {
                return $hit;
            }
        }

        return null;
    };

    $widgets = $find($nav, 'api/widgets');
    $orders = $find($nav, 'api/orders');

    expect($widgets)->not->toBeNull()
        ->and($orders)->not->toBeNull();

    $widgetSlugs = array_map(fn (TreeNode $child): string => $child->slug, $widgets->children);
    $orderSlugs = array_map(fn (TreeNode $child): string => $child->slug, $orders->children);

    expect($widgetSlugs)
        ->toContain('api/widgets/listwidgets')
        ->toContain('api/widgets/createwidget')
        ->and($orderSlugs)->toContain('api/orders/getorder');
})->with('openapi specs');

it('renders an operation page with method, path, parameters and schemas', function (string $version) {
    $this->makeDocs(['openapi.json' => widgetsSpecJson($version)]);

    $list = $this->get('/docs/api/widgets/listwidgets')->assertOk()->getContent();

    expect($list)
        ->toContain('GET')
        ->toContain('/widgets')
        ->toContain('Parameters')
        ->toContain('status')          // the query parameter
        ->toContain('Responses')
        ->toContain('name')            // a Widget property in the response schema
        ->toContain('kind');

    $create = $this->get('/docs/api/widgets/createwidget')->assertOk()->getContent();

    expect($create)
        ->toContain('POST')
        ->toContain('Request Body')
        ->toContain('name');
})->with('openapi specs');

it('expands $ref, allOf, oneOf, anyOf and enum in rendered schemas', function (string $version) {
    $this->makeDocs(['openapi.json' => widgetsSpecJson($version)]);

    // allOf merges the Identifiable $ref into Widget, so its `int64` format
    // surfaces alongside the inline members; enum and nullable are normalised.
    $list = $this->get('/docs/api/widgets/listwidgets')->assertOk()->getContent();

    expect($list)
        ->toContain('int64')           // Identifiable.id (proves $ref + allOf expansion)
        ->toContain('kind')
        ->toContain('Allowed values')  // enum label
        ->toContain('gadget')
        ->toContain('nullable');       // notes field, normalised from 3.0/3.1

    // oneOf on the request body payment field.
    $create = $this->get('/docs/api/widgets/createwidget')->assertOk()->getContent();

    expect($create)
        ->toContain('One of')
        ->toContain('cardNumber')
        ->toContain('amount');

    // anyOf on the order's source field.
    $order = $this->get('/docs/api/orders/getorder')->assertOk()->getContent();

    expect($order)->toContain('Any of');
})->with('openapi specs');

it('emits heading anchors and a populated table of contents', function (string $version) {
    $this->makeDocs(['openapi.json' => widgetsSpecJson($version)]);

    $html = $this->get('/docs/api/widgets/listwidgets')->assertOk()->getContent();

    expect($html)
        // Anchor targets emitted by the operation partial...
        ->toContain('id="parameters"')
        ->toContain('id="responses"')
        // ...and matching TOC links pointing at them.
        ->toContain('href="#parameters"')
        ->toContain('href="#responses"');
})->with('openapi specs');

it('indexes operations so they are searchable by path, summary and description', function (string $version) {
    $this->makeDocs(['openapi.json' => widgetsSpecJson($version)]);

    $slugs = fn (string $term): array => collect(
        $this->getJson("/docs/_laradocs/search?q={$term}")->assertOk()->json('results')
    )->pluck('slug')->all();

    // By description term, by summary term, and by a path/parameter token.
    expect($slugs('paginated'))->toContain('api/widgets/listwidgets')
        ->and($slugs('fetch'))->toContain('api/orders/getorder')
        ->and($slugs('orderId'))->toContain('api/orders/getorder');
})->with('openapi specs');

it('lists operation pages in the sitemap', function (string $version) {
    $this->makeDocs(['openapi.json' => widgetsSpecJson($version)]);

    $body = $this->get('/docs/sitemap.xml')->assertOk()->getContent();

    expect($body)
        ->toContain('<loc>' . url('/docs/api/widgets/listwidgets') . '</loc>')
        ->toContain('<loc>' . url('/docs/api/widgets/createwidget') . '</loc>')
        ->toContain('<loc>' . url('/docs/api/orders/getorder') . '</loc>');
})->with('openapi specs');

it('sets the SEO title and meta description from the operation', function (string $version) {
    $this->makeDocs(['openapi.json' => widgetsSpecJson($version)]);

    $this->get('/docs/api/widgets/listwidgets')
        ->assertOk()
        ->assertSee('<title>List all widgets · Acme Docs</title>', false)
        ->assertSee('Returns a paginated list of widgets.', false)
        ->assertSee('property="og:description"', false)
        ->assertSee('rel="canonical"', false);
})->with('openapi specs');

it('renders distinct cached bodies for operations with different shapes', function (string $version) {
    config()->set('laradocs.cache.enabled', true);

    $this->makeDocs(['openapi.json' => widgetsSpecJson($version)]);

    $list = $this->get('/docs/api/widgets/listwidgets')->assertOk()->getContent();
    $create = $this->get('/docs/api/widgets/createwidget')->assertOk()->getContent();

    // The HTML cache key folds in each operation's document path, so the two
    // never collapse onto a shared entry: list has parameters but no request
    // body, create has a request body but no parameters.
    expect($list)
        ->toContain('Parameters')
        ->not->toContain('Request Body')
        ->and($create)
        ->toContain('Request Body')
        ->not->toContain('Parameters');
})->with('openapi specs');

it('rebuilds tree, search and sitemap caches when the spec changes', function (string $version) {
    config()->set('laradocs.cache.enabled', true);

    $root = $this->makeDocs(['openapi.json' => widgetsSpecJson($version)]);

    // Warm every cached artifact.
    $this->get('/docs/sitemap.xml')->assertOk();
    $this->getJson('/docs/_laradocs/search?q=audits')->assertOk();
    $this->get('/docs/api')->assertOk();

    // The original spec has no "audits" operation anywhere yet.
    expect($this->get('/docs/sitemap.xml')->getContent())
        ->not->toContain('/docs/api/audits/listaudits');

    // Rewrite the spec with an extra operation and bump the mtime so the
    // mtime-folding cache keys bust.
    file_put_contents($root . '/openapi.json', widgetsSpecJson($version, extra: true));
    touch($root . '/openapi.json', time() + 60);
    clearstatcache();

    // Sitemap rebuilt.
    expect($this->get('/docs/sitemap.xml')->getContent())
        ->toContain('<loc>' . url('/docs/api/audits/listaudits') . '</loc>');

    // Search index rebuilt.
    $searchSlugs = collect($this->getJson('/docs/_laradocs/search?q=audits')->assertOk()->json('results'))
        ->pluck('slug')->all();
    expect($searchSlugs)->toContain('api/audits/listaudits');

    // Navigation tree rebuilt (the new section appears in the sidebar).
    expect($this->get('/docs/api')->assertOk()->getContent())->toContain('List audits');
})->with('openapi specs');

it('renders the same operation independently under two locales', function (string $version) {
    config()->set('laradocs.locale.available', ['en' => 'English', 'de' => 'Deutsch']);
    config()->set('laradocs.locale.default', 'en');
    config()->set('laradocs.cache.enabled', true);

    $this->makeDocs(['openapi.json' => widgetsSpecJson($version)]);

    $en = $this->get('/docs/api/widgets/listwidgets')->assertOk()->getContent();
    $de = $this->get('/docs/de/api/widgets/listwidgets')->assertOk()->getContent();

    // Each renders the operation in its own interface language; the locale is
    // folded into the document path, so the cached body of one never bleeds
    // into the other.
    expect($en)
        ->toContain('lang="en"')
        ->toContain('/widgets')
        ->toContain('List all widgets')
        ->and($de)
        ->toContain('lang="de"')
        ->toContain('/widgets')
        ->toContain('List all widgets')
        ->toContain('Dokumentation');  // German interface string, never on the en page
})->with('openapi specs');

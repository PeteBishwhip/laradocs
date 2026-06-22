import { expect, test } from '@playwright/test';

/**
 * JSON:API document-tree endpoint (ApiTreeController).
 *
 * `data` holds the top-level nodes; their descendants are flattened into a
 * compound-document `included` array, with parent→child links carried in each
 * node's relationships. The hidden fixture page
 * (docs/hidden-from-sitemap.md, front-matter `hidden: true`) is excluded from
 * the navigation tree, so it must appear in neither `data` nor `included`.
 */
const TREE = '/docs/_laradocs/api/tree';

test.describe('JSON:API tree endpoint', () => {
  test('returns a valid compound JSON:API document', async ({ request }) => {
    const response = await request.get(TREE);

    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('application/vnd.api+json');

    const body = await response.json();
    expect(body.jsonapi.version).toBe('1.0');
    expect(body.links.self).toContain('/docs/_laradocs/api/tree');

    // Top-level nodes are well-formed and expose a children relationship.
    expect(Array.isArray(body.data)).toBe(true);
    expect(body.data.length).toBeGreaterThan(0);
    for (const node of body.data) {
      expect(node.type).toBe('node');
      expect(typeof node.id).toBe('string');
      expect(typeof node.attributes.title).toBe('string');
      expect(typeof node.attributes.slug).toBe('string');
      expect(Array.isArray(node.relationships.children.data)).toBe(true);
    }
  });

  test('flattens descendants into a nested included array', async ({ request }) => {
    const body = await (await request.get(TREE)).json();

    expect(Array.isArray(body.included)).toBe(true);
    expect(body.included.length).toBeGreaterThan(0);

    // `included` is keyed by the nested node ids referenced by a parent's
    // children relationship — pick a parent that has children and confirm each
    // referenced child resolves to an included resource.
    const parent = body.data.find(
      (n: { relationships: { children: { data: unknown[] } } }) =>
        n.relationships.children.data.length > 0,
    );
    expect(parent, 'expected at least one branch node with children').toBeTruthy();

    const includedIds = new Set(body.included.map((n: { id: string }) => n.id));
    for (const ref of parent.relationships.children.data) {
      expect(ref.type).toBe('node');
      expect(includedIds.has(ref.id)).toBe(true);
    }

    // Included nodes are themselves nested (their ids carry a path segment).
    expect(body.included.some((n: { id: string }) => n.id.includes('/'))).toBe(true);
  });

  test('omits the hidden-from-sitemap page from the tree', async ({ request }) => {
    const body = await (await request.get(TREE)).json();

    const allNodes = [...body.data, ...(body.included ?? [])];
    const slugs = allNodes.map((n: { id: string; attributes: { slug: string } }) => n.id);
    const slugAttrs = allNodes.map(
      (n: { attributes: { slug: string } }) => n.attributes.slug,
    );

    expect(slugs).not.toContain('hidden-from-sitemap');
    expect(slugAttrs).not.toContain('hidden-from-sitemap');
  });
});

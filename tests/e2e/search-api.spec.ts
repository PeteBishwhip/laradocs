import { expect, test } from '@playwright/test';

/**
 * JSON:API search endpoint backing the ⌘K palette (ApiSearchController).
 *
 * The built-in JSON search engine is forced via LARADOCS_SEARCH_DRIVER=json in
 * playwright.config.ts — the default `auto` driver prefers Scout, whose
 * collection engine 500s under Testbench serve. With the JSON engine the
 * fixture docs (served via LARADOCS_PATH) produce deterministic ranked hits.
 */
const SEARCH = '/docs/_laradocs/api/search';

test.describe('JSON:API search endpoint', () => {
  test('returns a valid JSON:API envelope with page resources', async ({ request }) => {
    const response = await request.get(`${SEARCH}?q=search`);

    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('application/vnd.api+json');

    const body = await response.json();
    expect(body.jsonapi.version).toBe('1.0');
    expect(body.links.self).toContain('/docs/_laradocs/api/search');
    expect(Array.isArray(body.data)).toBe(true);
    expect(body.data.length).toBeGreaterThan(0);

    // Every resource is a well-formed `page` with the documented attributes.
    for (const resource of body.data) {
      expect(resource.type).toBe('page');
      expect(typeof resource.id).toBe('string');
      expect(resource.id).not.toBe('');
      const attrs = resource.attributes;
      expect(typeof attrs.title).toBe('string');
      expect(typeof attrs.slug).toBe('string');
      expect(typeof attrs.url).toBe('string');
      expect('group' in attrs).toBe(true);
      expect('excerpt' in attrs).toBe(true);
    }
  });

  test('keys the root document as _root', async ({ request }) => {
    // The slug-less root doc (docs/_index.md) matches "laradocs"; per
    // ApiSearchController an empty slug is exposed with id "_root".
    const response = await request.get(`${SEARCH}?q=laradocs`);
    const body = await response.json();

    const root = body.data.find((r: { id: string }) => r.id === '_root');
    expect(root, 'expected a _root resource for the slug-less root document').toBeTruthy();
    expect(root.attributes.slug).toBe('');
    // No non-root resource may smuggle in an empty slug under a real id.
    for (const resource of body.data) {
      if (resource.id !== '_root') {
        expect(resource.attributes.slug).not.toBe('');
      }
    }
  });

  test('returns an empty data set for an empty query', async ({ request }) => {
    const response = await request.get(`${SEARCH}?q=`);

    expect(response.status()).toBe(200);
    const body = await response.json();
    expect(body.jsonapi.version).toBe('1.0');
    expect(body.data).toEqual([]);
  });

  test('returns an empty data set below min_chars (2)', async ({ request }) => {
    const response = await request.get(`${SEARCH}?q=s`);

    expect(response.status()).toBe(200);
    const body = await response.json();
    expect(body.data).toEqual([]);
  });
});

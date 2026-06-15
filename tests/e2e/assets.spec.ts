import { test, expect } from '@playwright/test';

/**
 * The bundled stylesheet is served by the package's asset route
 * (AssetController). A regression here — a missing dist file or a wrong
 * Content-Type — would silently break every page's styling, so we hit the
 * endpoint directly and assert it responds 200 with a CSS content-type.
 */
test.describe('static asset endpoint', () => {
  test('serves laradocs.css as text/css', async ({ request }) => {
    const response = await request.get('/docs/_laradocs/asset/laradocs.css');

    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('text/css');
  });
});

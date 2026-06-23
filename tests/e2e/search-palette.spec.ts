import { expect, test } from '@playwright/test';

/**
 * The ⌘K command palette (initPalette in resources/dist/laradocs.js).
 *
 * Meta+K toggles the dialog; typing ≥ min_chars (2) debounces a fetch to the
 * palette search endpoint (/docs/_laradocs/search, SearchController) and renders
 * grouped result rows — a section header carrying the breadcrumb/section trail,
 * each row showing a highlighted title plus an excerpt. The built-in JSON
 * engine is forced via LARADOCS_SEARCH_DRIVER=json in playwright.config.ts so
 * the fixture docs produce real, ranked hits.
 */
const palette = '[data-laradocs-palette]';
const input = '[data-laradocs-palette-input]';
const results = '[data-laradocs-palette-results]';
const kbdTrigger = '[data-laradocs-kbd-trigger]';

test('Meta+K opens the palette and a hit navigates to its page', async ({ page }) => {
  await page.goto('/docs');

  await page.keyboard.press('Meta+k');
  await expect(page.locator(palette)).toBeVisible();

  // A multi-hit term: "search" matches several pages across sections.
  await page.locator(input).fill('search');

  // Remote results carry excerpts; the static fallback list does not, so the
  // appearance of an excerpt proves the fetched results have rendered.
  const excerpt = page.locator(`${results} .laradocs-palette-excerpt`).first();
  await expect(excerpt).toBeVisible();
  await expect(excerpt).not.toBeEmpty();

  // Breadcrumb/section context: hits are clustered under their ancestor section.
  await expect(page.locator(`${results} .laradocs-palette-section`).first()).toBeVisible();

  // Clicking a hit navigates to that page.
  await page.locator(`${results} a[href$="/docs/guide/search"]`).click();
  await expect(page).toHaveURL(/\/docs\/guide\/search$/);
});

test('Escape closes the palette', async ({ page }) => {
  await page.goto('/docs');

  await page.keyboard.press('Meta+k');
  await expect(page.locator(palette)).toBeVisible();

  await page.keyboard.press('Escape');
  await expect(page.locator(palette)).toBeHidden();
});

test('kbd trigger shows ⌘K on macOS', async ({ page }) => {
  await page.addInitScript(() => {
    Object.defineProperty(navigator, 'userAgentData', { get: () => ({ platform: 'macOS' }) });
  });
  await page.goto('/docs');
  await expect(page.locator(kbdTrigger)).toHaveText('⌘K');
});

test('kbd trigger shows Ctrl+K on Windows', async ({ page }) => {
  await page.addInitScript(() => {
    Object.defineProperty(navigator, 'userAgentData', { get: () => ({ platform: 'Windows' }) });
  });
  await page.goto('/docs');
  await expect(page.locator(kbdTrigger)).toHaveText('Ctrl+K');
});

test('kbd trigger shows Ctrl+K on Linux', async ({ page }) => {
  await page.addInitScript(() => {
    Object.defineProperty(navigator, 'userAgentData', { get: () => ({ platform: 'Linux' }) });
  });
  await page.goto('/docs');
  await expect(page.locator(kbdTrigger)).toHaveText('Ctrl+K');
});

test('a single-character query triggers no search request (min_chars=2)', async ({ page }) => {
  await page.goto('/docs');

  let searchRequests = 0;
  // Count palette-endpoint hits only — not the JSON:API /api/search route. A
  // request listener fires synchronously with the request event, so it has run
  // by the time waitForRequest resolves below (a route handler would race it).
  page.on('request', (req) => {
    if (/\/_laradocs\/search\?/.test(req.url())) searchRequests += 1;
  });

  await page.keyboard.press('Meta+k');
  await expect(page.locator(palette)).toBeVisible();

  // Below min_chars: must not fire a request (debounce is 150ms; wait it out).
  await page.locator(input).fill('s');
  await page.waitForTimeout(400);
  expect(searchRequests).toBe(0);

  // At/above min_chars the request does fire, proving the gate is the length.
  await page.locator(input).fill('search');
  await page.waitForRequest(/\/_laradocs\/search\?/);
  expect(searchRequests).toBeGreaterThan(0);
});

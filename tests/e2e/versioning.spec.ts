import { test, expect } from '@playwright/test';

/**
 * End-to-end coverage for the semantic doc-versioning UI.
 *
 * These specs run against the dedicated `versioning` Playwright project, which
 * boots a `testbench serve` instance on port 8004 pointed at the fixture tree
 * in tests/e2e/fixtures/versioned/ (v1.0.0/ + v2.0.0/, each with a
 * _version.json sidecar). The server enables versioning under the default auto
 * strategy, so the registry discovers both semver directories and flags v2.0.0
 * as the latest version. The relevant env (see playwright.config.ts):
 *
 *   LARADOCS_VERSIONS=true
 *   LARADOCS_VERSION_UNVERSIONED=redirect   → /docs redirects to the latest
 *   LARADOCS_VERSION_INLINE=true            → :::version-since blocks render
 *   LARADOCS_VERSION_OUTDATED_BANNER=true   → outdated banner on old versions
 *
 * v2.0.0 is the latest/default version; v1.0.0 is deprecated (_version.json).
 */

const V1 = '/docs/v1.0.0';
const V2 = '/docs/v2.0.0';

// Open the version picker <details> so its menu items become visible.
async function openPicker(page: import('@playwright/test').Page) {
  const picker = page.locator('[data-laradocs-version]');
  await expect(picker).toBeVisible();
  await picker.locator('summary').click();
  await expect(picker).toHaveAttribute('open', '');
}

// ─────────────────────────────────────────────────────────────────────────────
// Unversioned redirect
// ─────────────────────────────────────────────────────────────────────────────

test.describe('unversioned redirect', () => {
  test('visiting /docs redirects to the latest version (v2.0.0)', async ({
    page,
  }) => {
    await page.goto('/docs');

    // LARADOCS_VERSION_UNVERSIONED=redirect sends the bare prefix to the latest
    // version's root, which the auto strategy resolves to v2.0.0.
    await expect(page).toHaveURL(/\/docs\/v2\.0\.0\/?$/);
    await expect(page.locator('h1')).toContainText('v2.0.0');
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// Version picker
// ─────────────────────────────────────────────────────────────────────────────

test.describe('version picker', () => {
  test('is present and lists both versions', async ({ page }) => {
    await page.goto(V2);
    await openPicker(page);

    const menu = page.locator('.laradocs-version-menu');
    await expect(menu.locator('a[role="menuitem"]')).toHaveCount(2);
    await expect(menu.locator('a[href*="v1.0.0"]')).toBeVisible();
    await expect(menu.locator('a[href*="v2.0.0"]')).toBeVisible();
  });

  test('clicking v1.0.0 navigates to the v1 page', async ({ page }) => {
    await page.goto(V2);
    await openPicker(page);

    await page.locator('.laradocs-version-menu a[href*="v1.0.0"]').click();

    await expect(page).toHaveURL(/\/docs\/v1\.0\.0\/?$/);
    await expect(page.locator('h1')).toContainText('v1.0.0');
  });

  test('the v1.0.0 entry shows a deprecated badge', async ({ page }) => {
    await page.goto(V2);
    await openPicker(page);

    const v1 = page.locator('.laradocs-version-menu a[href*="v1.0.0"]');
    await expect(
      v1.locator('.laradocs-version-badge--deprecated'),
    ).toBeVisible();
    await expect(
      v1.locator('.laradocs-version-badge--deprecated'),
    ).toHaveText('deprecated');
  });

  test('the v2.0.0 entry shows a latest badge', async ({ page }) => {
    await page.goto(V2);
    await openPicker(page);

    const v2 = page.locator('.laradocs-version-menu a[href*="v2.0.0"]');
    await expect(v2.locator('.laradocs-version-badge--latest')).toBeVisible();
    await expect(v2.locator('.laradocs-version-badge--latest')).toHaveText(
      'latest',
    );
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// Outdated banner
// ─────────────────────────────────────────────────────────────────────────────

test.describe('outdated version banner', () => {
  test('is visible on v1.0.0 pages', async ({ page }) => {
    await page.goto(V1);

    const banner = page.locator('[data-laradocs-outdated-banner]');
    await expect(banner).toBeVisible();
    // Links back to the same page in the default (latest) version.
    await expect(banner.locator('a')).toHaveAttribute('href', /v2\.0\.0/);
  });

  test('is absent on v2.0.0 (the latest) pages', async ({ page }) => {
    await page.goto(V2);

    await expect(
      page.locator('[data-laradocs-outdated-banner]'),
    ).toHaveCount(0);
  });

  test('dismissing hides the banner and persists across v1 navigation', async ({
    page,
  }) => {
    await page.goto(V1);

    const banner = page.locator('[data-laradocs-outdated-banner]');
    await expect(banner).toBeVisible();

    await banner.locator('[data-laradocs-dismiss-version-banner]').click();
    await expect(banner).toBeHidden();

    // The dismissal is recorded in sessionStorage keyed by the active version.
    const stored = await page.evaluate(() =>
      sessionStorage.getItem('laradocs-banner-dismissed-v1.0.0'),
    );
    expect(stored).toBe('1');

    // Navigating to another v1 page keeps the banner hidden (the JS reads the
    // sessionStorage flag on load and hides it before paint).
    await page.goto(`${V1}/guide/configuration`);
    await expect(
      page.locator('[data-laradocs-outdated-banner]'),
    ).toBeHidden();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// Inline version-since block
// ─────────────────────────────────────────────────────────────────────────────

test.describe('inline version-since block', () => {
  const SELECTOR = '.version-block[data-version-since="2.0"]';

  test('is visible in v2.0.0 (current >= 2.0)', async ({ page }) => {
    await page.goto(`${V2}/guide/configuration`);

    const block = page.locator(SELECTOR);
    await expect(block).toBeVisible();
    await expect(block).toContainText('only visible from version 2.0');
  });

  test('is hidden in v1.0.0 (current < 2.0)', async ({ page }) => {
    await page.goto(`${V1}/guide/configuration`);

    // The block is still rendered server-side but carries the `hidden`
    // attribute; the client-side toggle leaves it hidden because v1.0.0 < 2.0.
    const block = page.locator(SELECTOR);
    await expect(block).toHaveCount(1);
    await expect(block).toBeHidden();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// Versions API
// ─────────────────────────────────────────────────────────────────────────────

test.describe('versions API', () => {
  test('GET /_laradocs/api/versions returns the expected JSON shape', async ({
    page,
  }) => {
    const response = await page.request.get('/docs/_laradocs/api/versions');
    expect(response.status()).toBe(200);

    const body = await response.json();
    expect(Array.isArray(body.versions)).toBe(true);
    expect(body.versions).toHaveLength(2);
    expect(body.default).toBe('v2.0.0');

    const byKey = Object.fromEntries(
      body.versions.map((v: { key: string }) => [v.key, v]),
    );

    expect(byKey['v2.0.0'].latest).toBe(true);
    expect(byKey['v2.0.0'].default).toBe(true);
    expect(byKey['v1.0.0'].deprecated).toBe(true);
    expect(byKey['v1.0.0'].latest).toBe(false);
  });
});

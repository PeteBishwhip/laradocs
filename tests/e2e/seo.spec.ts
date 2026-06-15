import { test, expect } from '@playwright/test';

/**
 * SEO metadata is rendered server-side via ralphjsmit/laravel-seo. We verify
 * the document root and a representative inner page both expose a title, a
 * canonical link, and Open Graph title/description tags so social/search
 * regressions are caught.
 */
const PAGES = ['/docs', '/docs/getting-started'];

for (const path of PAGES) {
  test.describe(`SEO meta on ${path}`, () => {
    test('exposes title, canonical, and Open Graph tags', async ({ page }) => {
      await page.goto(path);

      // Non-empty <title>.
      await expect(page).toHaveTitle(/.+/);

      // Canonical link with a real href.
      const canonical = page.locator('link[rel="canonical"]');
      await expect(canonical).toHaveCount(1);
      await expect(canonical).toHaveAttribute('href', /.+/);

      // Open Graph title + description.
      await expect(page.locator('meta[property="og:title"]')).toHaveAttribute(
        'content',
        /.+/,
      );
      await expect(
        page.locator('meta[property="og:description"]'),
      ).toHaveAttribute('content', /.+/);
    });
  });
}

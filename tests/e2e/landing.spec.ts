import { test, expect } from '@playwright/test';

/**
 * The docs root (`/docs`) is the entry point for every reader, so a regression
 * here — an empty title or a sidebar that fails to render the document tree —
 * is the most visible kind of breakage. We assert the page has a real title and
 * that the sidebar navigation lists at least one known fixture doc.
 */
test.describe('landing page', () => {
  test('renders a non-empty title and a populated sidebar', async ({ page }) => {
    await page.goto('/docs');

    // A real, non-empty <title>.
    await expect(page).toHaveTitle(/.+/);

    // The sidebar navigation should render the document tree. The viewport is
    // mobile here (off-canvas sidebar), so assert the link is in the DOM rather
    // than visually shown.
    const nav = page.locator('.laradocs-sidebar nav[aria-label="Documentation"]');
    await expect(nav.getByRole('link').first()).toBeAttached();
    await expect(
      nav.getByRole('link', { name: 'Getting Started' }),
    ).toBeAttached();
  });

  test('does not render the global banner without the banner env', async ({
    page,
  }) => {
    // This spec runs under the `default` project, which talks to the no-env
    // server on port 8000. With LARADOCS_BANNER unset the partial short-circuits
    // (`@if(! empty($banner['enabled']) ...)`), so the alert must be absent.
    // This is the negative counterpart to banner.spec.ts's presence assertion.
    await page.goto('/docs');

    await expect(page.locator('[role="alert"].laradocs-banner-alert')).toHaveCount(
      0,
    );
    await expect(page.locator('.laradocs-banner')).toHaveCount(0);
  });
});

import { expect, test } from '@playwright/test';

/**
 * Mobile navigation drawer (initMobileNav in resources/dist/laradocs.js). At a
 * narrow viewport the sidebar is off-canvas; the hamburger
 * (`[data-laradocs-menu]`) toggles `.nav-open` on `.laradocs-shell`, which
 * slides the sidebar in. It closes on backdrop click, on Escape, and on any
 * sidebar link click.
 *
 * The `default` Playwright project already carries a 390×844 mobile viewport;
 * we pin it here too so the spec is self-contained.
 */

test.describe('mobile navigation drawer', () => {
    test.use({ viewport: { width: 390, height: 844 } });

    test('opens via the hamburger and closes via the backdrop', async ({ page }) => {
        await page.goto('/docs');

        const shell = page.locator('.laradocs-shell');
        const hamburger = page.locator('[data-laradocs-menu]');
        const backdrop = page.locator('[data-laradocs-backdrop]');

        // Closed to start.
        await expect(shell).not.toHaveClass(/nav-open/);

        // Tap hamburger → open.
        await hamburger.click();
        await expect(shell).toHaveClass(/nav-open/);
        await expect(backdrop).toBeVisible();

        // Tap backdrop → closed.
        await backdrop.click();
        await expect(shell).not.toHaveClass(/nav-open/);
    });

    test('closes via Escape', async ({ page }) => {
        await page.goto('/docs');

        const shell = page.locator('.laradocs-shell');
        const hamburger = page.locator('[data-laradocs-menu]');

        await hamburger.click();
        await expect(shell).toHaveClass(/nav-open/);

        await page.keyboard.press('Escape');
        await expect(shell).not.toHaveClass(/nav-open/);
    });

    test('closes when a sidebar link is followed', async ({ page }) => {
        await page.goto('/docs');

        const shell = page.locator('.laradocs-shell');
        const hamburger = page.locator('[data-laradocs-menu]');

        await hamburger.click();
        await expect(shell).toHaveClass(/nav-open/);

        // The sidebar is slid in; click a top-level link.
        const link = page.locator('.laradocs-sidebar a[href$="/docs/getting-started"]');
        await expect(link).toBeVisible();
        await link.click();

        // Navigated to the target page, and the drawer is no longer open.
        await expect(page).toHaveURL(/\/docs\/getting-started$/);
        await expect(shell).not.toHaveClass(/nav-open/);
    });
});

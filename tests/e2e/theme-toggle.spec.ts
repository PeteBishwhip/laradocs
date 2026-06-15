import { expect, test } from '@playwright/test';

/**
 * Theme toggle (initTheme in resources/dist/laradocs.js). Clicking the
 * `[data-laradocs-theme-toggle]` button cycles through `auto → light → dark`
 * and back, persisting the choice in `localStorage['laradocs-theme']`.
 *
 * NOTE: the active theme is reflected on <html> via the `data-theme` *attribute*
 * (set to `light`/`dark`; removed entirely for `auto`), not a class — the JS
 * calls `documentElement.setAttribute('data-theme', …)`. The toggle button
 * mirrors the state in its own `data-theme-state` attribute.
 */

const STORAGE_KEY = 'laradocs-theme';

/** Read the current <html> theme attribute (null when in `auto`). */
function htmlTheme(page: import('@playwright/test').Page): Promise<string | null> {
    return page.evaluate(() => document.documentElement.getAttribute('data-theme'));
}

/** Read the persisted theme value. */
function storedTheme(page: import('@playwright/test').Page): Promise<string | null> {
    return page.evaluate((key) => window.localStorage.getItem(key), STORAGE_KEY);
}

test.describe('theme toggle', () => {
    test('cycles auto → light → dark → auto with persistence', async ({ page }) => {
        await page.goto('/docs');

        const toggle = page.locator('[data-laradocs-theme-toggle]');
        await expect(toggle).toBeAttached();

        // Initial state: auto. No data-theme attribute; localStorage unset
        // (treated as `auto` by currentTheme()).
        expect(await htmlTheme(page)).toBeNull();
        await expect(toggle).toHaveAttribute('data-theme-state', 'auto');
        expect(await storedTheme(page)).toBeNull();

        // Click 1: auto → light.
        await toggle.click();
        expect(await htmlTheme(page)).toBe('light');
        await expect(toggle).toHaveAttribute('data-theme-state', 'light');
        expect(await storedTheme(page)).toBe('light');

        // Click 2: light → dark.
        await toggle.click();
        expect(await htmlTheme(page)).toBe('dark');
        await expect(toggle).toHaveAttribute('data-theme-state', 'dark');
        expect(await storedTheme(page)).toBe('dark');

        // Click 3: dark → back to auto.
        await toggle.click();
        expect(await htmlTheme(page)).toBeNull();
        await expect(toggle).toHaveAttribute('data-theme-state', 'auto');
        expect(await storedTheme(page)).toBe('auto');

        // Persistence: cycling back to auto survives a reload.
        await page.reload();
        expect(await htmlTheme(page)).toBeNull();
        expect(await storedTheme(page)).toBe('auto');
        await expect(toggle).toHaveAttribute('data-theme-state', 'auto');
    });

    test('persists an explicit theme across a reload', async ({ page }) => {
        await page.goto('/docs');

        const toggle = page.locator('[data-laradocs-theme-toggle]');

        // Click twice to land on `dark`.
        await toggle.click();
        await toggle.click();
        expect(await htmlTheme(page)).toBe('dark');
        expect(await storedTheme(page)).toBe('dark');

        // Reload: the persisted `dark` is re-applied on boot.
        await page.reload();
        expect(await storedTheme(page)).toBe('dark');
        expect(await htmlTheme(page)).toBe('dark');
        await expect(toggle).toHaveAttribute('data-theme-state', 'dark');
    });
});

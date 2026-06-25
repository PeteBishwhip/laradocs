import { expect, test } from '@playwright/test';

/**
 * Regression test for mobile horizontal overflow on doc pages (issue #128).
 *
 * The palette trigger had an unconditional min-width: 14rem which kept it in
 * the header flex flow on narrow viewports, pushing the page width past the
 * viewport and producing horizontal scroll. Below 768 px the trigger now
 * collapses to an icon-only button so the header fits within the viewport.
 */
test.describe('mobile horizontal overflow', () => {
    test.use({ viewport: { width: 390, height: 844 } });

    test('doc page has no horizontal overflow at 390 px', async ({ page }) => {
        await page.goto('/docs');

        const overflow = await page.evaluate(() =>
            document.documentElement.scrollWidth > document.documentElement.clientWidth,
        );

        expect(overflow).toBe(false);
    });

    test('search trigger collapses to icon-only below 768 px', async ({ page }) => {
        await page.goto('/docs');

        const trigger = page.locator('.laradocs-palette-trigger');
        await expect(trigger).toBeVisible();

        // Text label and keyboard shortcut must be hidden on narrow viewports.
        await expect(trigger.locator('span')).toBeHidden();
        await expect(trigger.locator('kbd')).toBeHidden();
    });
});

import { expect, test } from '@playwright/test';

/**
 * On-page table of contents: the desktop right-rail TOC + scroll-spy, and the
 * mobile <details> collapse.
 *
 * `guide/routing` has eight headings (well over the `min_headings` threshold),
 * so both the right-rail `.laradocs-toc` and the `<details>.laradocs-toc-mobile`
 * render. The active-link tracking is driven by `initScrollSpy` in
 * resources/dist/laradocs.js, which toggles `is-active` on `.laradocs-toc a`.
 */

const FIRST_HEADING = '#filename-slugs';
const LAST_HEADING = '#named-routes';

test.describe('desktop right-rail TOC', () => {
    test.use({ viewport: { width: 1280, height: 900 } });

    test('renders TOC links and moves the scroll-spy active class on scroll', async ({ page }) => {
        await page.goto('/docs/guide/routing');

        const tocLinks = page.locator('.laradocs-toc a');
        await expect(tocLinks.first()).toBeVisible();
        expect(await tocLinks.count()).toBeGreaterThanOrEqual(2);
        await expect(page.locator(`.laradocs-toc a[href="${FIRST_HEADING}"]`)).toBeVisible();
        await expect(page.locator(`.laradocs-toc a[href="${LAST_HEADING}"]`)).toBeVisible();

        // Read the currently-active TOC link without waiting (there may be
        // none, e.g. when scrolled above the first heading's trigger point).
        const readActive = () =>
            page.evaluate(() => {
                const el = document.querySelector('.laradocs-toc a.is-active');
                return el ? el.getAttribute('href') : null;
            });

        // Near the top, the last heading is not the active one.
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.waitForTimeout(100); // let the scroll-spy rAF settle
        await expect(
            page.locator(`.laradocs-toc a[href="${LAST_HEADING}"]`),
        ).not.toHaveClass(/is-active/);
        const activeAtTop = await readActive();

        // Scrolling to the bottom moves the active class to the final heading.
        await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
        const activeLink = page.locator('.laradocs-toc a.is-active');
        await expect(activeLink).toHaveAttribute('href', LAST_HEADING);

        // The active link genuinely moved.
        expect(await readActive()).not.toBe(activeAtTop);
    });
});

test.describe('mobile TOC', () => {
    test.use({ viewport: { width: 390, height: 844 } });

    test('the <details> collapse toggles open and shut', async ({ page }) => {
        await page.goto('/docs/guide/routing');

        const details = page.locator('details.laradocs-toc-mobile');
        await expect(details).toBeVisible();

        // Starts collapsed.
        await expect(details).toHaveJSProperty('open', false);

        const summary = details.locator('summary');
        await summary.click();
        await expect(details).toHaveJSProperty('open', true);
        await expect(details.locator(`a[href="${FIRST_HEADING}"]`)).toBeVisible();

        await summary.click();
        await expect(details).toHaveJSProperty('open', false);
    });
});

import { expect, test } from '@playwright/test';

/**
 * @media print styles (resources/dist/laradocs.css).
 *
 * `page.emulateMedia({ media: 'print' })` activates the print media query so
 * we can assert against computed styles and element visibility without opening
 * a real browser print dialog.
 *
 * `navigation/routing` is a rich page: sticky header, sidebar, right-rail TOC
 * (8+ headings so it renders at ≥ 1180 px wide), breadcrumbs, prose body,
 * page-meta, pager, and footer — a good proxy for what a user would print.
 */

test.describe('print styles', () => {
    // Wide enough that the right-rail TOC renders in screen mode, making the
    // "TOC is hidden in print" assertion genuinely exercise the print rule
    // rather than the responsive one.
    test.use({ viewport: { width: 1280, height: 900 } });

    test.beforeEach(async ({ page }) => {
        await page.goto('/docs/navigation/routing');
        await page.emulateMedia({ media: 'print' });
    });

    test('hides non-content chrome', async ({ page }) => {
        await expect(page.locator('.laradocs-header')).toBeHidden();
        await expect(page.locator('.laradocs-sidebar')).toBeHidden();
        await expect(page.locator('.laradocs-toc')).toBeHidden();
        await expect(page.locator('.laradocs-toc-mobile')).toBeHidden();
        await expect(page.locator('.laradocs-progress')).toBeHidden();
        await expect(page.locator('.laradocs-palette')).toBeHidden();
        await expect(page.locator('[data-laradocs-theme-toggle]')).toBeHidden();
    });

    test('keeps prose content visible', async ({ page }) => {
        await expect(page.locator('.laradocs-prose')).toBeVisible();
        await expect(page.locator('.laradocs-page-title')).toBeVisible();
        await expect(page.locator('.laradocs-breadcrumbs')).toBeVisible();
    });

    test('expands content to fill the page width', async ({ page }) => {
        const { contentWidth, bodyWidth } = await page.evaluate(() => ({
            contentWidth: (document.querySelector('.laradocs-content') as HTMLElement)
                .getBoundingClientRect().width,
            bodyWidth: document.body.getBoundingClientRect().width,
        }));
        // In screen mode the content column is capped at --dc-content-w (46 rem ≈ 736 px
        // at default font-size). In print mode the shell and content reset to
        // display:block / max-width:100%, so the column should fill ≥ 90% of the body.
        expect(contentWidth).toBeGreaterThan(bodyWidth * 0.9);
    });

    test('forces light-mode --dc-bg even when dark theme is active', async ({ page }) => {
        // Re-enter screen mode, switch the theme to dark, then re-enter print.
        await page.emulateMedia({ media: 'screen' });
        await page.evaluate(() =>
            document.documentElement.setAttribute('data-theme', 'dark'),
        );
        await page.emulateMedia({ media: 'print' });

        // The print block overrides --dc-bg to #ffffff on every dark-mode selector,
        // so the resolved value must be the light-mode white regardless of theme.
        const dcBg = await page.evaluate(() =>
            getComputedStyle(document.documentElement).getPropertyValue('--dc-bg').trim(),
        );
        expect(dcBg).toBe('#ffffff');
    });

    test('annotates external prose links with their URL', async ({ page }) => {
        const externalLinks = page.locator('.laradocs-prose a[href^="http"]');
        const count = await externalLinks.count();
        test.skip(count === 0, 'no external http links on this page to assert against');

        const href = await externalLinks.first().getAttribute('href');
        // CSS `content: " (" attr(href) ")"` — getComputedStyle resolves attr()
        // at computed-style time, so the string carries the actual href value.
        const afterContent = await externalLinks.first().evaluate((el) =>
            window.getComputedStyle(el, '::after').content,
        );
        expect(afterContent).toContain(href!);
    });
});

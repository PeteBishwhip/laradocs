import { expect, test } from '@playwright/test';

/**
 * Navigation chrome: breadcrumbs, prev/next pager, and sidebar ordering.
 *
 * The fixture lives in the repo's `docs/` tree (served via LARADOCS_PATH in
 * playwright.config.ts). `guide/routing` is a nested page whose breadcrumb
 * chain, pager neighbours, and sidebar position are all deterministic and
 * mirror the PHP `Navigation::breadcrumbs`/`siblings` output.
 *
 * A desktop viewport is forced so the sidebar (off-canvas on the project's
 * default mobile viewport) is rendered and clickable.
 */
test.use({ viewport: { width: 1280, height: 900 } });

const sidebarNav = '.laradocs-sidebar nav[aria-label="Documentation"]';

test('nested sidebar link navigates and renders the breadcrumb chain', async ({ page }) => {
    await page.goto('/docs/guide');

    // Nested link click → URL.
    await page.locator(`${sidebarNav} a[href$="/docs/guide/routing"]`).click();
    await expect(page).toHaveURL(/\/docs\/guide\/routing$/);

    // Breadcrumb chain: Home · Guide · Routing.
    const breadcrumbs = page.locator('.laradocs-breadcrumbs');
    await expect(breadcrumbs).toBeVisible();
    await expect(breadcrumbs.locator('a', { hasText: 'Home' })).toBeVisible();
    await expect(breadcrumbs.locator('a', { hasText: 'Guide' })).toBeVisible();
    const crumbText = (await breadcrumbs.innerText()).replace(/\s+/g, ' ').trim();
    expect(crumbText).toMatch(/^Home\s*·\s*Guide\s*·\s*Routing$/);
});

test('prev/next pager points at the alphabetical siblings of the nested page', async ({ page }) => {
    await page.goto('/docs/guide/routing');

    const pager = page.locator('.laradocs-pager');
    await expect(pager).toBeVisible();

    // Matches Navigation::siblings over the (default-alphabetical) guide tree:
    // …Robots → Routing → Search…
    const prev = pager.locator('a.prev');
    const next = pager.locator('a.next');
    await expect(prev).toHaveAttribute('href', /\/docs\/guide\/robots$/);
    await expect(next).toHaveAttribute('href', /\/docs\/guide\/search$/);
});

test('sidebar order honours explicit order at the top level', async ({ page }) => {
    await page.goto('/docs/guide');

    // Top-level (single-segment slug) links in their rendered order. Explicit
    // `order:` front-matter drives this: Getting Started (2) → System
    // Requirements (3) → Configuration (4) → Guide (5) → Features (6) → API
    // Reference (7). Note this is NOT alphabetical — "Configuration" would sort
    // before "Getting Started".
    const topLevel = await page
        .locator(`${sidebarNav} a[href*="/docs/"]`)
        .evaluateAll((links) =>
            links
                .filter((el) => {
                    const slug = (el.getAttribute('href') ?? '').split('/docs/')[1] ?? '';
                    return slug.length > 0 && !slug.includes('/');
                })
                .map((el) => (el.textContent ?? '').trim()),
        );

    const expectedHead = ['Getting Started', 'System Requirements', 'Configuration', 'Guide', 'Features', 'API Reference'];
    expect(topLevel.slice(0, expectedHead.length)).toEqual(expectedHead);

    // Explicit order beats alphabetical default.
    expect(topLevel.indexOf('Getting Started')).toBeLessThan(topLevel.indexOf('Configuration'));
});

test('sidebar order falls back to alphabetical for default-ordered children', async ({ page }) => {
    await page.goto('/docs/guide');

    // The guide section's children carry no explicit `order`, so they sort
    // alphabetically by title (case-insensitive).
    const guideChildren = await page
        .locator(`${sidebarNav} a[href*="/docs/guide/"]`)
        .evaluateAll((links) => links.map((el) => (el.textContent ?? '').trim()));

    expect(guideChildren).toEqual([
        'Analytics',
        'Caching',
        'CLI',
        'Grouping',
        'Localisation',
        'MCP Server',
        'Metadata',
        'PHP API',
        'Robots',
        'Routing',
        'Search',
        'SEO',
        'Sitemap',
        'Tags',
        'Versioning',
    ]);

    // Confirm it really is alphabetical (the default-order branch), comparing
    // on the same case-insensitive key the PHP sort uses.
    const sorted = [...guideChildren].sort((a, b) =>
        a.toLowerCase().localeCompare(b.toLowerCase()),
    );
    expect(guideChildren).toEqual(sorted);
});

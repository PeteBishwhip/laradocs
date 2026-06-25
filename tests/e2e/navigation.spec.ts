import { expect, test } from '@playwright/test';

/**
 * Navigation chrome: breadcrumbs, prev/next pager, and sidebar ordering.
 *
 * The fixture lives in the repo's `docs/` tree (served via LARADOCS_PATH in
 * playwright.config.ts). `navigation/routing` is a nested page whose breadcrumb
 * chain, pager neighbours, and sidebar position are all deterministic and
 * mirror the PHP `Navigation::breadcrumbs`/`siblings` output.
 *
 * A desktop viewport is forced so the sidebar (off-canvas on the project's
 * default mobile viewport) is rendered and clickable.
 */
test.use({ viewport: { width: 1280, height: 900 } });

const sidebarNav = '.laradocs-sidebar nav[aria-label="Documentation"]';

test('nested sidebar link navigates and renders the breadcrumb chain', async ({ page }) => {
    await page.goto('/docs/navigation');

    // Nested link click → URL.
    await page.locator(`${sidebarNav} a[href$="/docs/navigation/routing"]`).click();
    await expect(page).toHaveURL(/\/docs\/navigation\/routing$/);

    // Breadcrumb chain: Home · Navigation · Routing.
    const breadcrumbs = page.locator('.laradocs-breadcrumbs');
    await expect(breadcrumbs).toBeVisible();
    await expect(breadcrumbs.locator('a', { hasText: 'Home' })).toBeVisible();
    await expect(breadcrumbs.locator('a', { hasText: 'Navigation' })).toBeVisible();
    const crumbText = (await breadcrumbs.innerText()).replace(/\s+/g, ' ').trim();
    expect(crumbText).toMatch(/^Home\s*·\s*Navigation\s*·\s*Routing$/);
});

test('prev/next pager points at the ordered siblings of the nested page', async ({ page }) => {
    await page.goto('/docs/navigation/metadata');

    const pager = page.locator('.laradocs-pager');
    await expect(pager).toBeVisible();

    // Matches Navigation::siblings over the navigation tree, whose children
    // carry explicit `order:` front-matter: Routing (1) → Grouping (2) →
    // Metadata (3) → Tags (4) → Search (5). Metadata sits between Grouping and
    // Tags.
    const prev = pager.locator('a.prev');
    const next = pager.locator('a.next');
    await expect(prev).toHaveAttribute('href', /\/docs\/navigation\/grouping$/);
    await expect(next).toHaveAttribute('href', /\/docs\/navigation\/tags$/);
});

test('sidebar order honours explicit order at the top level', async ({ page }) => {
    await page.goto('/docs/navigation');

    // Top-level (single-segment slug) links in their rendered order. Explicit
    // `order:` front-matter drives this: Getting Started (2) → System
    // Requirements (3) → Configuration (4) → Deployment (5) → Content (6) →
    // Navigation (7) → SEO (8) → Customisation (9) → Advanced (10) →
    // Integrations (11) → API Reference (12) → CLI (13). Note this is NOT
    // alphabetical — "Configuration" would sort before "Getting Started".
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

    const expectedHead = [
        'Getting Started',
        'System Requirements',
        'Configuration',
        'Deployment',
        'Content',
        'Navigation',
        'SEO',
        'Customisation',
        'Advanced',
        'Integrations',
        'API Reference',
        'CLI',
    ];
    expect(topLevel.slice(0, expectedHead.length)).toEqual(expectedHead);

    // Explicit order beats alphabetical default.
    expect(topLevel.indexOf('Getting Started')).toBeLessThan(topLevel.indexOf('Configuration'));
});

test('nested sidebar children honour explicit order over alphabetical', async ({ page }) => {
    await page.goto('/docs/navigation');

    // The navigation section's children carry explicit `order:` front-matter,
    // so they render in that sequence rather than alphabetically.
    const navChildren = await page
        .locator(`${sidebarNav} a[href*="/docs/navigation/"]`)
        .evaluateAll((links) => links.map((el) => (el.textContent ?? '').trim()));

    expect(navChildren).toEqual([
        'Routing',
        'Grouping',
        'Metadata',
        'Tags',
        'Search',
    ]);

    // Confirm explicit order really is winning: the alphabetical fallback would
    // have produced a different sequence.
    const alphabetical = [...navChildren].sort((a, b) =>
        a.toLowerCase().localeCompare(b.toLowerCase()),
    );
    expect(navChildren).not.toEqual(alphabetical);
});

import { expect, test } from '@playwright/test';

/**
 * XML sitemap: `/docs/sitemap.xml` is rendered by SitemapBuilder from the live
 * document tree. Every visible, non-redirecting page is emitted in tree order
 * as a <url> with a <loc>, an optional <lastmod> (ISO-8601) and a <priority>
 * that falls off with depth. Hidden pages (front-matter `hidden: true`, e.g.
 * the docs/hidden-from-sitemap.md fixture) are excluded.
 *
 * Playwright ships no XML parser, so we pull the <url> blocks apart with small
 * regexes — enough to assert structure without a heavyweight dependency.
 */

interface SitemapEntry {
    loc: string;
    lastmod: string | null;
    priority: string | null;
}

/** Split the urlset into one record per <url>…</url> block. */
function parseSitemap(xml: string): SitemapEntry[] {
    const entries: SitemapEntry[] = [];
    const blocks = xml.match(/<url>[\s\S]*?<\/url>/g) ?? [];

    for (const block of blocks) {
        const loc = block.match(/<loc>([\s\S]*?)<\/loc>/);
        const lastmod = block.match(/<lastmod>([\s\S]*?)<\/lastmod>/);
        const priority = block.match(/<priority>([\s\S]*?)<\/priority>/);

        entries.push({
            loc: loc ? loc[1] : '',
            lastmod: lastmod ? lastmod[1] : null,
            priority: priority ? priority[1] : null,
        });
    }

    return entries;
}

// Known fixture slugs that must appear in the sitemap (relative to /docs).
const EXPECTED_SLUGS = ['getting-started', 'configuration', 'navigation/routing', 'navigation/search'];

test('sitemap.xml lists the fixture pages and excludes the hidden fixture', async ({ request }) => {
    const response = await request.get('/docs/sitemap.xml');

    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('application/xml');

    const xml = await response.text();
    expect(xml).toContain('<urlset');

    const entries = parseSitemap(xml);
    expect(entries.length).toBeGreaterThan(0);

    // Every entry advertises a canonical loc and a priority.
    for (const entry of entries) {
        expect(entry.loc).toMatch(/^https?:\/\/.+\/docs(\/|$)/);
        expect(entry.priority).not.toBeNull();
        expect(entry.priority).toMatch(/^[01]\.\d$/);
    }

    const locs = entries.map((entry) => entry.loc);

    // The root doc (empty slug) is present as the bare /docs URL.
    expect(locs.some((loc) => /\/docs\/?$/.test(loc))).toBe(true);

    // Known fixture slugs are present.
    for (const slug of EXPECTED_SLUGS) {
        expect(locs.some((loc) => loc.endsWith(`/docs/${slug}`))).toBe(true);
    }

    // At least one entry carries a valid ISO-8601 lastmod.
    const gettingStarted = entries.find((entry) => entry.loc.endsWith('/docs/getting-started'));
    expect(gettingStarted).toBeDefined();
    expect(gettingStarted!.lastmod).not.toBeNull();
    expect(gettingStarted!.lastmod).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/);
    // Depth-1 pages get priority 0.8.
    expect(gettingStarted!.priority).toBe('0.8');

    // The hidden fixture (docs/hidden-from-sitemap.md) must not appear anywhere.
    expect(xml).not.toContain('hidden-from-sitemap');
    expect(locs.some((loc) => loc.includes('hidden-from-sitemap'))).toBe(false);
});

import { expect, test } from '@playwright/test';

/**
 * llms.txt: `/docs/llms.txt` is rendered by LlmsTxtBuilder from the live
 * document tree. The file opens with an H1 naming the site, then lists every
 * visible, non-redirecting page as `- [Title](absolute url): description`,
 * grouped under one H2 per top-level navigation section in tree order. Pages
 * belonging to no section are gathered under a leading "## Docs" heading, and
 * hidden pages (front-matter `hidden: true`, e.g. the
 * docs/hidden-from-sitemap.md fixture) are excluded.
 */

interface Bullet {
    title: string;
    url: string;
    description: string | null;
}

/** Parse every `- [Title](url): description` bullet out of the file. */
function parseBullets(body: string): Bullet[] {
    const bullets: Bullet[] = [];

    for (const line of body.split('\n')) {
        const match = line.match(/^- \[(.+?)\]\((\S+?)\)(?:: (.*))?$/);

        if (match) {
            bullets.push({ title: match[1], url: match[2], description: match[3] ?? null });
        }
    }

    return bullets;
}

/** Ordered list of the file's `## ` section headings. */
function parseHeadings(body: string): string[] {
    return body
        .split('\n')
        .filter((line) => line.startsWith('## '))
        .map((line) => line.slice(3));
}

// Known fixture slugs that must appear in the index (relative to /docs).
const EXPECTED_SLUGS = ['getting-started', 'configuration', 'navigation/routing', 'navigation/search'];

test('llms.txt indexes the fixture pages and excludes the hidden fixture', async ({ request }) => {
    const response = await request.get('/docs/llms.txt');

    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('text/plain');

    const body = await response.text();

    // The format requires an H1 as the very first line.
    expect(body.split('\n')[0]).toMatch(/^# .+/);

    const bullets = parseBullets(body);
    expect(bullets.length).toBeGreaterThan(0);

    // Every bullet advertises an absolute URL inside the docs prefix.
    for (const bullet of bullets) {
        expect(bullet.url).toMatch(/^https?:\/\/.+\/docs(\/|$)/);
        expect(bullet.title.length).toBeGreaterThan(0);
    }

    const urls = bullets.map((bullet) => bullet.url);

    // The root doc (empty slug) is present as the bare /docs URL.
    expect(urls.some((url) => /\/docs\/?$/.test(url))).toBe(true);

    // Known fixture slugs are present.
    for (const slug of EXPECTED_SLUGS) {
        expect(urls.some((url) => url.endsWith(`/docs/${slug}`))).toBe(true);
    }

    // Pages with no section of their own lead the file.
    const headings = parseHeadings(body);
    expect(headings[0]).toBe('Docs');

    // Sections are real navigation sections, so a nested fixture's section
    // heading is present and its pages sit under it.
    expect(headings).toContain('Navigation');
    const navigationHeading = body.indexOf('\n## Navigation\n');
    const routing = body.indexOf('/docs/navigation/routing');
    expect(navigationHeading).toBeGreaterThan(-1);
    expect(routing).toBeGreaterThan(navigationHeading);

    // At least one bullet carries a description.
    expect(bullets.some((bullet) => bullet.description !== null && bullet.description.length > 0)).toBe(true);

    // The hidden fixture (docs/hidden-from-sitemap.md) must not appear anywhere.
    expect(body).not.toContain('hidden-from-sitemap');
});

test('llms.txt is not served from the site root unless opted in', async ({ request }) => {
    // laradocs.llms.root defaults to false, so the package must not claim a
    // path outside its own prefix.
    const response = await request.get('/llms.txt');

    expect(response.status()).toBe(404);
});

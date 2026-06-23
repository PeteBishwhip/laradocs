import { test, expect } from '@playwright/test';
import { LOCALE_SERVER, COOKIE_SERVER } from '../../playwright.config';

/**
 * Locale / language-switcher e2e tests.
 *
 * Two dedicated servers are used:
 *
 *  - LOCALE_SERVER (port 8002) — URL-path locales ON (the default), cookie OFF.
 *    The active locale lives in the path (/docs/de/...); the default locale is
 *    served unprefixed and a legacy ?lang= query 301-redirects to the path form.
 *    Internal links carry the locale segment so readers stay in the chosen
 *    language as they navigate.
 *
 *  - COOKIE_SERVER (port 8003) — legacy mode: URL-path locales OFF, cookie ON.
 *    An explicit ?lang= choice writes a one-year `laradocs_locale` cookie;
 *    subsequent navigation reads it, keeping the URL clean (no ?lang= query).
 *
 * Both servers expose exactly two locales (en / de) via LARADOCS_LOCALE_AVAILABLE
 * and disable Accept-Language detection (LARADOCS_DETECT_BROWSER=false) so the
 * browser environment does not influence which locale is selected.
 *
 * German UI strings used as locale indicators:
 *   search trigger  "Durchsuche die Dokumente..."  (en: "Search the docs...")
 *   language label  "Sprache"                      (en: "Language")
 */

// ─────────────────────────────────────────────────────────────────────────────
// Language selector
// ─────────────────────────────────────────────────────────────────────────────

test.describe('language selector', () => {
  test('is visible when multiple locales are available', async ({ page }) => {
    await page.goto(`${LOCALE_SERVER}/docs`);

    await expect(page.locator('[data-laradocs-lang]')).toBeVisible();
  });

  test('lists every available locale with its label', async ({ page }) => {
    await page.goto(`${LOCALE_SERVER}/docs`);

    // Open the <details> dropdown so menu items are visible. Locators are
    // scoped to the selector because the page <head> also emits hreflang
    // alternate <link> tags carrying the same attribute.
    await page.locator('[data-laradocs-lang] summary').click();

    await expect(page.locator('[data-laradocs-lang] [hreflang="en"]')).toBeVisible();
    await expect(page.locator('[data-laradocs-lang] [hreflang="de"]')).toBeVisible();
    await expect(page.locator('[data-laradocs-lang] [hreflang="en"]')).toContainText('English');
    await expect(page.locator('[data-laradocs-lang] [hreflang="de"]')).toContainText('Deutsch');
  });

  test('marks the active locale with aria-current and updates the summary label', async ({ page }) => {
    await page.goto(`${LOCALE_SERVER}/docs/de`);

    await page.locator('[data-laradocs-lang] summary').click();

    // The active entry carries aria-current="true".
    await expect(page.locator('[data-laradocs-lang] [hreflang="de"]')).toHaveAttribute('aria-current', 'true');
    await expect(page.locator('[data-laradocs-lang] [hreflang="en"]')).not.toHaveAttribute('aria-current', 'true');

    // The collapsed summary shows the human-readable label for the active locale.
    await expect(page.locator('.laradocs-lang-current')).toContainText('Deutsch');
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// Locale in the URL path (cookie persistence OFF)
// ─────────────────────────────────────────────────────────────────────────────

test.describe('locale in the URL path (no cookie)', () => {
  test('/docs/de renders German UI strings', async ({ page }) => {
    await page.goto(`${LOCALE_SERVER}/docs/de`);

    // The search trigger button text is a reliable locale indicator — it is
    // visible on every page and translated in the German language file.
    await expect(page.locator('.laradocs-palette-trigger')).toContainText(
      'Durchsuche die Dokumente...',
    );
  });

  test('a legacy ?lang=de 301-redirects to the path form', async ({ page }) => {
    await page.goto(`${LOCALE_SERVER}/docs?lang=de`);

    // The query is rewritten to the canonical path, and the page renders German.
    await expect(page).toHaveURL(`${LOCALE_SERVER}/docs/de`);
    await expect(page.locator('.laradocs-palette-trigger')).toContainText(
      'Durchsuche die Dokumente...',
    );
  });

  test('an unknown locale code is ignored and the default locale is used', async ({ page }) => {
    await page.goto(`${LOCALE_SERVER}/docs?lang=xx`);

    // "xx" is not in the available list so English (the default) is rendered.
    await expect(page.locator('.laradocs-palette-trigger')).toContainText(
      'Search the docs...',
    );
  });

  test('sidebar links carry the locale segment so it survives navigation', async ({
    page,
  }) => {
    await page.goto(`${LOCALE_SERVER}/docs/de`);

    // Every internal link DocumentUrl generates should carry the /de/ segment
    // when the active locale is not the default.
    const sidebarLinks = page.locator('aside.laradocs-sidebar a');
    await expect(sidebarLinks.first()).toBeVisible();

    const hrefs = await sidebarLinks.evaluateAll((els) =>
      (els as HTMLAnchorElement[]).map((el) => el.getAttribute('href') ?? ''),
    );

    expect(hrefs.some((h) => /\/docs\/de(\/|$)/.test(h))).toBe(true);
    // The legacy query string must never appear in a generated link.
    expect(hrefs.every((h) => !h.includes('lang='))).toBe(true);
  });

  test('following a locale-scoped sidebar link preserves the German locale', async ({ page }) => {
    await page.goto(`${LOCALE_SERVER}/docs/de`);

    // Click the first sidebar link that carries the /docs/de/ segment.
    const target = page.locator('aside.laradocs-sidebar a[href*="/docs/de/"]').first();
    await expect(target).toBeVisible();
    await target.click();

    // The linked page must also render in German — the locale segment is
    // picked up by the middleware on the new request.
    await expect(page.locator('.laradocs-palette-trigger')).toContainText(
      'Durchsuche die Dokumente...',
    );
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// Cookie persistence (cookie persistence ON)
// ─────────────────────────────────────────────────────────────────────────────

test.describe('cookie persistence', () => {
  test('selecting a language via ?lang= sets the laradocs_locale cookie', async ({
    page,
    context,
  }) => {
    await page.goto(`${COOKIE_SERVER}/docs?lang=de`);

    const cookies = await context.cookies(COOKIE_SERVER);
    const localeCookie = cookies.find((c) => c.name === 'laradocs_locale');

    // The cookie is written on an explicit choice. Its raw value is the
    // Laravel-encrypted payload (EncryptCookies middleware), not the plain
    // "de" — the server decrypts it transparently on the next request. We
    // therefore assert the cookie exists, has a non-empty value and a long
    // (one-year) lifetime; the "preserves the locale across navigation" test
    // below proves the decrypted value actually drives the rendered language.
    expect(localeCookie).toBeDefined();
    expect(localeCookie?.value).toBeTruthy();

    // ~one year ahead (cookie('laradocs_locale', …, 60 * 24 * 365) minutes).
    const oneYearFromNow = Date.now() / 1000 + 60 * 60 * 24 * 365;
    expect(localeCookie?.expires).toBeGreaterThan(oneYearFromNow - 60 * 60 * 24); // allow a day of slack
  });

  test('the cookie preserves the locale across navigation without ?lang=', async ({ page }) => {
    // First visit — sets the laradocs_locale=de cookie via ?lang=de.
    await page.goto(`${COOKIE_SERVER}/docs?lang=de`);

    // Navigate to another docs page without any ?lang= parameter.
    await page.goto(`${COOKIE_SERVER}/docs`);

    // The cookie carries the locale — the page must still render in German.
    await expect(page.locator('.laradocs-palette-trigger')).toContainText(
      'Durchsuche die Dokumente...',
    );
  });

  test('internal links are clean (no ?lang=) when the cookie carries the locale', async ({
    page,
  }) => {
    await page.goto(`${COOKIE_SERVER}/docs?lang=de`);

    // When cookie persistence is on, DocumentUrl omits ?lang= from links
    // because the cookie is the persistence mechanism.
    const sidebarLinks = page.locator('aside.laradocs-sidebar a');
    await expect(sidebarLinks.first()).toBeVisible();

    const hrefs = await sidebarLinks.evaluateAll((els) =>
      (els as HTMLAnchorElement[]).map((el) => el.getAttribute('href') ?? ''),
    );

    expect(hrefs.every((h) => !h.includes('lang='))).toBe(true);
  });

  test('browsing without making a language choice does not set the cookie', async ({
    page,
    context,
  }) => {
    // A plain visit with no ?lang= and no existing cookie must not write
    // laradocs_locale — the cookie is only set on an explicit choice.
    await page.goto(`${COOKIE_SERVER}/docs`);

    const cookies = await context.cookies(COOKIE_SERVER);
    const localeCookie = cookies.find((c) => c.name === 'laradocs_locale');

    expect(localeCookie).toBeUndefined();
  });
});

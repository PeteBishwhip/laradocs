import { test, expect } from '@playwright/test';
import { BANNER_MESSAGE } from '../../playwright.config';

/**
 * The configurable global banner only renders when LARADOCS_BANNER is enabled,
 * which only the `banner` Playwright project does (it talks to the second
 * `composer serve` on port 8001 with the LARADOCS_BANNER_* env set). This spec
 * is matched solely to that project via `testMatch: /banner\.spec\.ts/` in
 * playwright.config.ts, so it always runs against the banner-enabled server.
 *
 * banner.blade.php renders the message with `{!! $message !!}` — i.e. unescaped
 * — so the <a> embedded in the configured message must be a live anchor element,
 * not escaped text. We assert exactly that.
 *
 * (The absence of the banner under the default project is asserted in
 * landing.spec.ts, which runs against the no-env server on port 8000.)
 */
test.describe('global banner (banner project)', () => {
  test('renders the configured alert banner with a live anchor', async ({
    page,
  }) => {
    await page.goto('/docs');

    // With LARADOCS_BANNER_TYPE='alert', the blade emits
    // `class="laradocs-banner laradocs-banner-alert" role="alert"`.
    const banner = page.locator('[role="alert"].laradocs-banner-alert');
    await expect(banner).toBeVisible();

    // The configured message text is present (the anchor's link text is part of
    // the rendered copy — HTML tags are stripped from the accessible text).
    await expect(banner).toContainText('upgrade guide');
    await expect(banner).toContainText('before continuing');

    // The <a> in the message must be a real anchor element, not escaped markup.
    // If `{!! !!}` were ever swapped for `{{ }}`, this anchor would not exist
    // (the tag would render as literal `&lt;a&gt;` text) and this would fail.
    const link = banner.locator('a', { hasText: 'upgrade guide' });
    await expect(link).toHaveCount(1);
    await expect(link).toHaveAttribute('href', 'https://laradocs.test/upgrade');

    // Sanity-check the message wired through config is the one being rendered:
    // the inner container's HTML should contain the raw anchor markup verbatim.
    const innerHtml = await banner
      .locator('.laradocs-banner-inner')
      .innerHTML();
    expect(innerHtml).toContain(BANNER_MESSAGE);
  });

  /**
   * On desktop the sidebar is `position: fixed` just below the sticky header
   * (and section tabs). The banner sits above the header and scrolls away, so
   * the sidebar must be offset by however much banner is still on screen —
   * otherwise the header covers the top of the sidebar until the banner has
   * scrolled out of view.
   */
  test('keeps the sidebar below the header while the banner is on screen', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 1280, height: 720 });
    await page.goto('/docs/advanced/localisation');

    const banner = page.locator('.laradocs-banner-alert');
    const header = page.locator('.laradocs-header');
    const sidebar = page.locator('aside.laradocs-sidebar');
    await expect(banner).toBeVisible();
    await expect(sidebar).toBeVisible();

    // The lowest sticky bar is the section tabs when present, else the header.
    const tabs = page.locator('.laradocs-tabs');
    const lowestBar = (await tabs.count()) > 0 ? tabs : header;

    const expectSidebarBelowBars = async () => {
      await expect
        .poll(async () => {
          const bars = await lowestBar.boundingBox();
          const nav = await sidebar.boundingBox();
          return Math.round(nav!.y - (bars!.y + bars!.height));
        })
        .toBeGreaterThanOrEqual(-1);
    };

    // At the top of the page the banner pushes the header down.
    const bannerBox = await banner.boundingBox();
    expect(bannerBox!.height).toBeGreaterThan(0);
    await expectSidebarBelowBars();

    // Part-way through the banner: the offset shrinks with it.
    await page.evaluate((y) => window.scrollTo(0, y), Math.floor(bannerBox!.height / 2));
    await expectSidebarBelowBars();

    // Once the banner has scrolled away, the sidebar returns flush to the bars.
    await page.evaluate(() => window.scrollTo(0, 600));
    await expectSidebarBelowBars();
    await expect
      .poll(async () => {
        const bars = await lowestBar.boundingBox();
        const nav = await sidebar.boundingBox();
        return Math.abs(Math.round(nav!.y - (bars!.y + bars!.height)));
      })
      .toBeLessThanOrEqual(1);
  });
});

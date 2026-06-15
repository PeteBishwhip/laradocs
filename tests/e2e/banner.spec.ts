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
});

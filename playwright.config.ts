import { defineConfig, devices } from '@playwright/test';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

/**
 * Under Testbench, base_path() points at the empty skeleton app rather than this
 * package, so `composer serve` wouldn't pick up the real docs/ folder. The PHP
 * built-in server also resolves relative paths against its document root, so a
 * relative LARADOCS_PATH fails at request time. We hand both serve instances an
 * absolute path to this repo's docs/ so the e2e specs assert against the real
 * fixture pages.
 */
const repoRoot = dirname(fileURLToPath(import.meta.url));
const LARADOCS_PATH = resolve(repoRoot, 'docs');

/**
 * The `banner` project boots a second `composer serve` instance configured to
 * render the global banner so banner.spec.ts can assert against real markup —
 * including the anchor tag embedded in the message.
 */
const BANNER_MESSAGE =
  'Heads up: read the <a href="https://laradocs.test/upgrade">upgrade guide</a> before continuing.';

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  reporter: 'html',
  use: {
    baseURL: 'http://127.0.0.1:8000/docs',
    trace: 'on-first-retry',
  },
  webServer: [
    {
      command: 'composer serve',
      url: 'http://127.0.0.1:8000/docs',
      reuseExistingServer: !process.env.CI,
      env: { LARADOCS_PATH },
    },
    {
      command: 'composer serve --port=8001',
      url: 'http://127.0.0.1:8001/docs',
      reuseExistingServer: !process.env.CI,
      env: {
        LARADOCS_PATH,
        LARADOCS_BANNER: 'true',
        LARADOCS_BANNER_TYPE: 'alert',
        LARADOCS_BANNER_MESSAGE: BANNER_MESSAGE,
      },
    },
  ],
  projects: [
    {
      // Default desktop project. Owns mobile-nav.spec.ts, so it carries the
      // mobile viewport; banner.spec.ts is excluded and handled below.
      name: 'default',
      testIgnore: /banner\.spec\.ts/,
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 390, height: 844 },
      },
    },
    {
      // Talks to the banner-enabled server on port 8001.
      name: 'banner',
      testMatch: /banner\.spec\.ts/,
      use: {
        ...devices['Desktop Chrome'],
        baseURL: 'http://127.0.0.1:8001/docs',
      },
    },
  ],
});

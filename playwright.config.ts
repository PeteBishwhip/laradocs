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
 * The `versioning` project boots a dedicated `testbench serve` instance pointed
 * at the hermetic fixture tree in tests/e2e/fixtures/versioned/ (v1.0.0/ +
 * v2.0.0/, each with a _version.json sidecar). Versioning is enabled with the
 * auto strategy, so the registry discovers both semver dirs and flags v2.0.0 as
 * latest. The env mirrors a real versioned site: unversioned URLs redirect to
 * the latest version, inline version-since blocks toggle client-side, and the
 * outdated banner shows on the older version.
 */
const VERSIONED_PATH = resolve(repoRoot, 'tests/e2e/fixtures/versioned');
export const VERSIONING_SERVER = 'http://127.0.0.1:8004';

/**
 * The search "driver" defaults to `auto`, which prefers Laravel Scout when it's
 * installed (it is, in vendor/). Under Testbench serve the Scout collection
 * engine blows up (SearchableDocument::query() is undefined), 500-ing both the
 * palette endpoint and /api/search. Forcing the built-in JSON engine gives the
 * search specs (and the ⌘K palette) a working full-text backend.
 */
const LARADOCS_SEARCH_DRIVER = 'json';

/**
 * The `banner` project boots a second `composer serve` instance configured to
 * render the global banner so banner.spec.ts can assert against real markup —
 * including the anchor tag embedded in the message.
 */
export const BANNER_MESSAGE =
  'Heads up: read the <a href="https://laradocs.test/upgrade">upgrade guide</a> before continuing.';

/**
 * Locale servers for locale.spec.ts.
 *
 * Two locales (en + de) are injected via LARADOCS_LOCALE_AVAILABLE rather than
 * publishing lang files to the Testbench skeleton, so the test is hermetic and
 * doesn't touch the shared filesystem state. The bundled translations in
 * resources/lang/ are loaded automatically via loadTranslationsFrom() and are
 * enough for the German strings to render correctly.
 *
 * LARADOCS_DETECT_BROWSER is forced off on both servers so a CI runner's
 * Accept-Language header cannot interfere with assertions.
 *
 * Port 8002 — URL-path locales ON (the default), cookie persistence OFF. Tests
 * that the locale lives in the path (/docs/de/...), that a legacy ?lang= query
 * 301-redirects to the path form, and that internal links carry the segment.
 *
 * Port 8003 — legacy mode: URL-path locales OFF, cookie persistence ON. Tests
 * that an explicit ?lang= choice sets the cookie, that subsequent navigation
 * reads it, and that internal links are clean (no ?lang=) because the cookie
 * carries the state instead.
 */
export const LOCALE_AVAILABLE = JSON.stringify({ en: 'English', de: 'Deutsch' });
export const LOCALE_SERVER = 'http://127.0.0.1:8002';
export const COOKIE_SERVER = 'http://127.0.0.1:8003';

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
      env: { LARADOCS_PATH, LARADOCS_SEARCH_DRIVER },
    },
    {
      // `composer serve --port=8001` does NOT work: the composer `serve` script
      // is a multi-step array (@build then `testbench serve`), and the trailing
      // `--port` is not forwarded to the serve step — the server still binds
      // 8000, colliding with the default server above. We build, then invoke
      // `testbench serve` directly so the port actually applies.
      command: 'composer build && vendor/bin/testbench serve --ansi --port=8001',
      url: 'http://127.0.0.1:8001/docs',
      reuseExistingServer: !process.env.CI,
      env: {
        LARADOCS_PATH,
        LARADOCS_SEARCH_DRIVER,
        LARADOCS_BANNER: 'true',
        LARADOCS_BANNER_TYPE: 'alert',
        LARADOCS_BANNER_MESSAGE: BANNER_MESSAGE,
      },
    },
    {
      command: 'composer build && vendor/bin/testbench serve --ansi --port=8002',
      url: `${LOCALE_SERVER}/docs`,
      reuseExistingServer: !process.env.CI,
      env: {
        LARADOCS_PATH,
        LARADOCS_SEARCH_DRIVER,
        LARADOCS_LOCALE_AVAILABLE: LOCALE_AVAILABLE,
        LARADOCS_DETECT_BROWSER: 'false',
      },
    },
    {
      command: 'composer build && vendor/bin/testbench serve --ansi --port=8003',
      url: `${COOKIE_SERVER}/docs`,
      reuseExistingServer: !process.env.CI,
      env: {
        LARADOCS_PATH,
        LARADOCS_SEARCH_DRIVER,
        LARADOCS_LOCALE_AVAILABLE: LOCALE_AVAILABLE,
        LARADOCS_LOCALE_COOKIE: 'true',
        // Legacy query/cookie mode: the cookie is only meaningful when the
        // locale isn't already pinned in the URL path.
        LARADOCS_LOCALE_URL: 'false',
        LARADOCS_DETECT_BROWSER: 'false',
      },
    },
    {
      command: 'composer build && vendor/bin/testbench serve --ansi --port=8004',
      url: `${VERSIONING_SERVER}/docs/v2.0.0`,
      reuseExistingServer: !process.env.CI,
      env: {
        LARADOCS_PATH: VERSIONED_PATH,
        LARADOCS_SEARCH_DRIVER,
        LARADOCS_VERSIONS: 'true',
        LARADOCS_VERSION_UNVERSIONED: 'redirect',
        LARADOCS_VERSION_INLINE: 'true',
        LARADOCS_VERSION_OUTDATED_BANNER: 'true',
      },
    },
  ],
  projects: [
    {
      // Default desktop project. Owns mobile-nav.spec.ts, so it carries the
      // mobile viewport; banner.spec.ts and locale.spec.ts are excluded and
      // handled by their dedicated projects below.
      name: 'default',
      testIgnore: /banner\.spec\.ts|locale\.spec\.ts|versioning\.spec\.ts/,
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
    {
      // Locale tests use absolute URLs internally (two servers: 8002 and 8003)
      // so no project-level baseURL is needed.
      name: 'locale',
      testMatch: /locale\.spec\.ts/,
      use: { ...devices['Desktop Chrome'] },
    },
    {
      // Talks to the versioning-enabled server on port 8004 (fixture tree with
      // v1.0.0 + v2.0.0). baseURL targets /docs so unversioned-redirect specs
      // can navigate to the bare prefix.
      name: 'versioning',
      testMatch: /versioning\.spec\.ts/,
      use: {
        ...devices['Desktop Chrome'],
        baseURL: `${VERSIONING_SERVER}/docs`,
      },
    },
  ],
});

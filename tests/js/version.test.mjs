import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';

const scriptSource = readFileSync(
  new URL('../../resources/dist/laradocs.js', import.meta.url),
  'utf8',
);

/**
 * Build a fresh JSDOM around `body`, set the active version on the window, eval
 * the bundled script, and wait for the DOMContentLoaded boot to run so
 * initVersionBlocks()/initVersionBanner() have executed. Each test gets its own
 * isolated DOM so listeners and storage never leak across tests.
 */
async function boot(body, { version } = {}) {
  const dom = new JSDOM(
    `<!doctype html><html><body>${body}</body></html>`,
    { runScripts: 'outside-only', pretendToBeVisual: true, url: 'https://docs.test/' },
  );

  const { window } = dom;
  window.Element.prototype.scrollIntoView = function () {};
  if (version !== undefined) window.__laradocsVersion = version;

  window.eval(scriptSource);

  if (window.document.readyState === 'loading') {
    await new Promise((resolve) => {
      window.document.addEventListener('DOMContentLoaded', resolve, { once: true });
    });
  }

  return { window, document: window.document };
}

const BLOCKS = `
  <div class="version-block" data-version-since="2.0" hidden>since-2.0</div>
  <div class="version-block" data-version-until="2.0" hidden>until-2.0</div>
  <div class="version-block" data-version-only="1.0, 1.1" hidden>only-1.0-1.1</div>
`;

function visible(document, selector) {
  return !document.querySelector(selector).hasAttribute('hidden');
}

test('initVersionBlocks shows since[2.0] blocks when the current version is >= 2.0', async () => {
  const { document } = await boot(BLOCKS, { version: 'v2.0' });
  assert.equal(visible(document, '[data-version-since="2.0"]'), true);
});

test('initVersionBlocks keeps until[2.0] blocks hidden when the current version is >= 2.0', async () => {
  const { document } = await boot(BLOCKS, { version: 'v2.0' });
  assert.equal(visible(document, '[data-version-until="2.0"]'), false);
});

test('initVersionBlocks shows until[2.0] blocks when the current version is < 2.0', async () => {
  const { document } = await boot(BLOCKS, { version: 'v1.0' });
  assert.equal(visible(document, '[data-version-until="2.0"]'), true);
});

test('initVersionBlocks shows since[2.0] blocks hidden when the current version is < 2.0', async () => {
  const { document } = await boot(BLOCKS, { version: 'v1.0' });
  assert.equal(visible(document, '[data-version-since="2.0"]'), false);
});

test('initVersionBlocks shows only[1.0, 1.1] blocks only when the current version is in the list', async () => {
  const matching = await boot(BLOCKS, { version: 'v1.1' });
  assert.equal(visible(matching.document, '[data-version-only]'), true);

  const other = await boot(BLOCKS, { version: 'v2.0' });
  assert.equal(visible(other.document, '[data-version-only]'), false);
});

test('initVersionBlocks leaves all blocks hidden when no version is active', async () => {
  const { document } = await boot(BLOCKS);
  assert.equal(visible(document, '[data-version-since="2.0"]'), false);
  assert.equal(visible(document, '[data-version-until="2.0"]'), false);
  assert.equal(visible(document, '[data-version-only]'), false);
});

const BANNER = `
  <div data-laradocs-outdated-banner role="alert">
    Outdated
    <button type="button" data-laradocs-dismiss-version-banner>Dismiss</button>
  </div>
`;

test('clicking dismiss hides the banner and stores the per-version key in sessionStorage', async () => {
  const { window, document } = await boot(BANNER, { version: 'v1.0' });
  const banner = document.querySelector('[data-laradocs-outdated-banner]');
  assert.equal(banner.hidden, false);

  document.querySelector('[data-laradocs-dismiss-version-banner]').click();

  assert.equal(banner.hidden, true);
  assert.equal(window.sessionStorage.getItem('laradocs-banner-dismissed-v1.0'), '1');
});

test('a previously dismissed banner (same session) is hidden immediately on init', async () => {
  // Seed sessionStorage on a first DOM, then boot a second DOM that shares the
  // origin-keyed store to mimic a same-session reload.
  const first = await boot(BANNER, { version: 'v1.0' });
  first.document.querySelector('[data-laradocs-dismiss-version-banner]').click();

  // Reuse the seeded value by writing it before the second boot.
  const dom = new JSDOM(`<!doctype html><html><body>${BANNER}</body></html>`, {
    runScripts: 'outside-only',
    pretendToBeVisual: true,
    url: 'https://docs.test/',
  });
  dom.window.sessionStorage.setItem('laradocs-banner-dismissed-v1.0', '1');
  dom.window.__laradocsVersion = 'v1.0';
  dom.window.eval(scriptSource);
  if (dom.window.document.readyState === 'loading') {
    await new Promise((resolve) => {
      dom.window.document.addEventListener('DOMContentLoaded', resolve, { once: true });
    });
  }

  assert.equal(dom.window.document.querySelector('[data-laradocs-outdated-banner]').hidden, true);
});

test('a fresh session (empty sessionStorage) leaves the banner visible', async () => {
  const { document } = await boot(BANNER, { version: 'v1.0' });
  assert.equal(document.querySelector('[data-laradocs-outdated-banner]').hidden, false);
});

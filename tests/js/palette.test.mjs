import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';

const rawScriptSource = readFileSync(
  new URL('../../resources/dist/laradocs.js', import.meta.url),
  'utf8',
);

// jsdom's window.location is non-configurable and assigning to .href emits a
// "navigation not implemented" error that silently no-ops. Rewrite the one
// assignment in the palette so Enter records its target on window.__navTo
// instead — that's the single side-effect we actually want to assert on.
const NAV_PATTERN = /window\.location\.href = visible\[activeIndex\]\.href/;
if (!NAV_PATTERN.test(rawScriptSource)) {
  throw new Error('Test setup is stale: navigation assignment not found in palette source.');
}
const scriptSource = rawScriptSource.replace(
  NAV_PATTERN,
  'window.__navTo = visible[activeIndex].href',
);

const PRERENDERED_ITEMS = `
  <li><a href="/docs/install" data-label="installation">Installation</a></li>
  <li><a href="/docs/config" data-label="configuration">Configuration</a></li>
  <li><a href="/docs/search" data-label="search">Search</a></li>
`;

/**
 * Build a fresh JSDOM around the palette markup, eval the bundled script in
 * it, and return handles into the resulting window. Each test gets its own
 * isolated DOM so listeners and state never leak across tests.
 *
 * Passing `searchUrl: null` mimics the search-disabled blade path where the
 * data attributes are omitted, exercising the local-only fallback.
 */
async function bootPalette({ searchUrl = '/_laradocs/search', minChars = 2 } = {}) {
  const dataAttrs = searchUrl
    ? `data-laradocs-search-url="${searchUrl}" data-laradocs-search-min="${minChars}"`
    : '';

  const dom = new JSDOM(
    `<!doctype html><html><body>
      <div class="laradocs-palette" data-laradocs-palette hidden role="dialog" ${dataAttrs}>
        <input data-laradocs-palette-input type="text" />
        <ul data-laradocs-palette-results>${PRERENDERED_ITEMS}</ul>
      </div>
    </body></html>`,
    { runScripts: 'outside-only', pretendToBeVisual: true },
  );

  const { window } = dom;

  // jsdom does not implement scrollIntoView; setActive() calls it on every
  // navigation. Stub it so the highlighted item just stays where it is.
  window.Element.prototype.scrollIntoView = function () {};

  // Run the IIFE inside the jsdom window. `outside-only` permits this and
  // keeps inline-script execution off, which keeps the harness predictable.
  window.eval(scriptSource);

  // jsdom starts in readyState='loading' and emits DOMContentLoaded on the
  // next macrotask; wait for it before driving the palette so initPalette
  // has actually run and attached its listeners (and only once).
  if (window.document.readyState === 'loading') {
    await new Promise((resolve) => {
      window.document.addEventListener('DOMContentLoaded', resolve, { once: true });
    });
  }

  return {
    window,
    document: window.document,
    input: window.document.querySelector('[data-laradocs-palette-input]'),
    palette: window.document.querySelector('[data-laradocs-palette]'),
    results: window.document.querySelector('[data-laradocs-palette-results]'),
  };
}

function tick(window, ms = 0) {
  return new Promise((resolve) => window.setTimeout(resolve, ms));
}

function visibleHrefs(results) {
  return [...results.querySelectorAll('li')]
    .filter((li) => !li.hasAttribute('hidden'))
    .map((li) => li.querySelector('a')?.getAttribute('href'));
}

function fireInput(window, input, value) {
  input.value = value;
  input.dispatchEvent(new window.Event('input'));
}

function pressKey(window, target, key, init = {}) {
  target.dispatchEvent(new window.KeyboardEvent('keydown', { key, bubbles: true, cancelable: true, ...init }));
}

test('local fallback: filters pre-rendered items by title when no search URL is exposed', async () => {
  const { window, input, results } = await bootPalette({ searchUrl: null });

  fireInput(window, input, 'install');

  assert.deepEqual(visibleHrefs(results), ['/docs/install']);

  fireInput(window, input, '');
  assert.deepEqual(visibleHrefs(results), ['/docs/install', '/docs/config', '/docs/search']);
});

test('short queries restore the pre-rendered list without firing a request', async () => {
  const { window, input, results } = await bootPalette({ minChars: 3 });
  let calls = 0;
  window.fetch = () => { calls += 1; return Promise.resolve({ json: () => ({ results: [] }) }); };

  fireInput(window, input, 'in');
  await tick(window, 200);

  assert.equal(calls, 0, 'fetch must not fire below min_chars');
  assert.deepEqual(visibleHrefs(results), ['/docs/install', '/docs/config', '/docs/search']);
});

test('remote search is debounced and rendered with title, section header and excerpt', async () => {
  const { window, input, results } = await bootPalette();
  const calls = [];
  window.fetch = (url) => {
    calls.push(url);
    return Promise.resolve({
      json: () => ({
        results: [{
          slug: 'guide/install',
          title: 'Installation',
          group: 'Guide',
          breadcrumb: ['Guide'],
          url: '/docs/guide/install',
          excerpt: '…run composer require…',
        }],
      }),
    });
  };

  fireInput(window, input, 'co');
  fireInput(window, input, 'com');
  fireInput(window, input, 'comp');

  assert.equal(calls.length, 0, 'debounce should swallow intermediate keystrokes');

  await tick(window, 200);

  assert.equal(calls.length, 1, 'exactly one request fires after the debounce window');
  assert.match(calls[0], /\?q=comp$/);

  // One section header ("Guide") followed by one result row.
  const section = results.querySelector('.laradocs-palette-section');
  assert.equal(section.textContent, 'Guide');
  assert.equal(section.getAttribute('aria-hidden'), 'true');
  assert.equal(results.querySelectorAll('li a').length, 1, 'the header carries no anchor');

  const row = results.querySelector('li a');
  assert.equal(row.querySelector('.laradocs-palette-title').textContent, 'Installation');
  // The section is the only crumb, so no per-row breadcrumb is repeated.
  assert.equal(row.querySelector('.laradocs-palette-breadcrumb'), null);
  assert.equal(row.querySelector('.laradocs-palette-excerpt').textContent, '…run composer require…');
  assert.equal(row.getAttribute('href'), '/docs/guide/install');
});

test('matched query terms are wrapped in <mark> within the title and excerpt', async () => {
  const { window, input, results } = await bootPalette();
  window.fetch = () => Promise.resolve({
    json: () => ({
      results: [{
        slug: 'guide/install',
        title: 'Composer Install',
        breadcrumb: ['Guide'],
        url: '/docs/guide/install',
        excerpt: 'Run composer require to add it.',
      }],
    }),
  });

  fireInput(window, input, 'composer');
  await tick(window, 200);

  // Case-insensitive: "Composer" in the title and "composer" in the excerpt
  // both highlight, and the surrounding text is preserved verbatim.
  const titleMark = results.querySelector('.laradocs-palette-title mark');
  assert.equal(titleMark.textContent, 'Composer');
  assert.equal(titleMark.className, 'laradocs-palette-mark');
  assert.equal(results.querySelector('.laradocs-palette-title').textContent, 'Composer Install');

  const excerptMark = results.querySelector('.laradocs-palette-excerpt mark');
  assert.equal(excerptMark.textContent, 'composer');
  assert.equal(results.querySelector('.laradocs-palette-excerpt').textContent, 'Run composer require to add it.');
});

test('every whitespace-separated term highlights, and regex metacharacters are matched literally', async () => {
  const { window, input, results } = await bootPalette();
  window.fetch = () => Promise.resolve({
    json: () => ({
      results: [{
        slug: 'a',
        title: 'Map c() over a.b list',
        breadcrumb: [],
        url: '/docs/a',
        excerpt: '',
      }],
    }),
  });

  // "c()" and "a.b" contain regex metacharacters; they must match literally,
  // not as patterns — and both terms highlight independently.
  fireInput(window, input, 'c() a.b');
  await tick(window, 200);

  const marks = [...results.querySelectorAll('.laradocs-palette-title mark')].map((m) => m.textContent);
  assert.deepEqual(marks, ['c()', 'a.b']);
});

test('hits are grouped under section headers, preserving rank order across sections', async () => {
  const { window, input, results } = await bootPalette();
  window.fetch = () => Promise.resolve({
    json: () => ({
      results: [
        { slug: 'guide/install', title: 'Install', breadcrumb: ['Guide'], url: '/docs/guide/install', excerpt: '' },
        { slug: 'api/auth', title: 'Auth', breadcrumb: ['API'], url: '/docs/api/auth', excerpt: '' },
        { slug: 'guide/config', title: 'Config', breadcrumb: ['Guide'], url: '/docs/guide/config', excerpt: '' },
      ],
    }),
  });

  fireInput(window, input, 'in');
  await tick(window, 200);

  // Guide appears first (top-ranked hit), and its second hit is pulled up
  // beside the first rather than re-emitting the header. API follows.
  const order = [...results.querySelectorAll('li')].map((li) =>
    li.classList.contains('laradocs-palette-section')
      ? `#${li.textContent}`
      : li.querySelector('a').getAttribute('href'),
  );
  assert.deepEqual(order, ['#Guide', '/docs/guide/install', '/docs/guide/config', '#API', '/docs/api/auth']);

  // Exactly two headers, one per distinct section.
  assert.equal(results.querySelectorAll('.laradocs-palette-section').length, 2);
});

test('a deeper breadcrumb trail renders beneath the section header on the row', async () => {
  const { window, input, results } = await bootPalette();
  window.fetch = () => Promise.resolve({
    json: () => ({
      results: [{
        slug: 'guide/advanced/routing',
        title: 'Routing',
        breadcrumb: ['Guide', 'Advanced'],
        url: '/docs/guide/advanced/routing',
        excerpt: '',
      }],
    }),
  });

  fireInput(window, input, 'route');
  await tick(window, 200);

  assert.equal(results.querySelector('.laradocs-palette-section').textContent, 'Guide');
  // The leading crumb is the header; the remainder hangs off the row.
  assert.equal(results.querySelector('.laradocs-palette-breadcrumb').textContent, 'Advanced');
});

test('sectionless results render flat, falling back to a flat group when no breadcrumb is sent', async () => {
  const { window, input, results } = await bootPalette();
  window.fetch = () => Promise.resolve({
    json: () => ({
      results: [
        // No breadcrumb at all and no group → no header, listed flat.
        { slug: 'overview', title: 'Overview', url: '/docs/overview', excerpt: '' },
        // Legacy payload: only a flat group, used as the section fallback.
        { slug: 'legacy', title: 'Legacy', group: 'Reference', url: '/docs/legacy', excerpt: '' },
      ],
    }),
  });

  fireInput(window, input, 'ov');
  await tick(window, 200);

  const order = [...results.querySelectorAll('li')].map((li) =>
    li.classList.contains('laradocs-palette-section')
      ? `#${li.textContent}`
      : li.querySelector('a').getAttribute('href'),
  );
  assert.deepEqual(order, ['/docs/overview', '#Reference', '/docs/legacy']);
});

test('keyboard navigation skips section headers and lands only on result rows', async () => {
  const { window, document, input, palette, results } = await bootPalette();
  window.fetch = () => Promise.resolve({
    json: () => ({
      results: [
        { slug: 'guide/install', title: 'Install', breadcrumb: ['Guide'], url: '/docs/guide/install', excerpt: '' },
        { slug: 'api/auth', title: 'Auth', breadcrumb: ['API'], url: '/docs/api/auth', excerpt: '' },
      ],
    }),
  });

  palette.removeAttribute('hidden');
  input.focus();

  fireInput(window, input, 'in');
  await tick(window, 200);

  const links = () => [...results.querySelectorAll('a')];
  assert.equal(links()[0].classList.contains('is-active'), true, 'first row active despite the leading header');

  // Two sections, two headers, two rows — ArrowDown moves header-to-header
  // across rows without ever activating a header.
  pressKey(window, document, 'ArrowDown');
  assert.equal(links()[1].classList.contains('is-active'), true);
  assert.equal(results.querySelector('.laradocs-palette-section.is-active'), null);

  pressKey(window, document, 'Enter');
  assert.equal(window.__navTo, '/docs/api/auth');
});

test('empty server result renders the "No results" placeholder', async () => {
  const { window, input, results } = await bootPalette();
  window.fetch = () => Promise.resolve({ json: () => ({ results: [] }) });

  fireInput(window, input, 'zzz');
  await tick(window, 200);

  const empty = results.querySelector('.laradocs-palette-empty');
  assert.ok(empty, 'empty placeholder is rendered');
  assert.equal(empty.textContent, 'No results');
});

test('stale responses are dropped: later request wins even when it resolves first', async () => {
  const { window, input, results } = await bootPalette();
  let resolveFirst;
  let firstSeen = false;
  let secondSeen = false;
  window.fetch = (url) => {
    if (url.endsWith('q=foo')) {
      firstSeen = true;
      return new Promise((resolve) => { resolveFirst = resolve; });
    }
    secondSeen = true;
    return Promise.resolve({
      json: () => ({
        results: [{ slug: 'b', title: 'Bar', group: '', url: '/docs/bar', excerpt: '' }],
      }),
    });
  };

  fireInput(window, input, 'foo');
  await tick(window, 200);
  assert.ok(firstSeen, 'first request fires');

  // Trigger a second request that resolves immediately and wins.
  fireInput(window, input, 'bar');
  await tick(window, 200);
  assert.ok(secondSeen, 'second request fires');

  // Now resolve the stale first request — it must not overwrite the list.
  resolveFirst({ json: () => ({
    results: [{ slug: 'a', title: 'Foo', group: '', url: '/docs/foo', excerpt: '' }],
  }) });
  await tick(window, 0);

  const titles = [...results.querySelectorAll('.laradocs-palette-title')].map((el) => el.textContent);
  assert.deepEqual(titles, ['Bar'], 'rendered list reflects the latest query only');
});

test('network failure restores the pre-rendered list and applies local filter', async () => {
  const { window, input, results } = await bootPalette();
  window.fetch = () => Promise.reject(new Error('boom'));

  fireInput(window, input, 'install');
  await tick(window, 200);

  // Catch path restores originalHtml and then filters by query on title.
  assert.deepEqual(visibleHrefs(results), ['/docs/install']);
});

test('ArrowDown / ArrowUp cycle the active result, Enter navigates', async () => {
  const { window, document, input, palette, results } = await bootPalette();
  window.fetch = () => Promise.resolve({
    json: () => ({
      results: [
        { slug: 'a', title: 'Alpha', group: '', url: '/docs/a', excerpt: '' },
        { slug: 'b', title: 'Beta', group: '', url: '/docs/b', excerpt: '' },
        { slug: 'c', title: 'Gamma', group: '', url: '/docs/c', excerpt: '' },
      ],
    }),
  });

  // Open the palette and focus the input so Enter triggers navigation.
  palette.removeAttribute('hidden');
  input.focus();

  fireInput(window, input, 'al');
  await tick(window, 200);

  const links = () => [...results.querySelectorAll('a')];
  assert.equal(links()[0].classList.contains('is-active'), true, 'first item is active by default');

  pressKey(window, document, 'ArrowDown');
  assert.equal(links()[1].classList.contains('is-active'), true);

  pressKey(window, document, 'ArrowDown');
  pressKey(window, document, 'ArrowDown'); // clamps at the last
  assert.equal(links()[2].classList.contains('is-active'), true);

  pressKey(window, document, 'ArrowUp');
  assert.equal(links()[1].classList.contains('is-active'), true);

  pressKey(window, document, 'Enter');
  assert.equal(window.__navTo, '/docs/b');
});

test('Cmd+K toggles the palette open and closed', async () => {
  const { window, document, palette } = await bootPalette();
  assert.ok(palette.hasAttribute('hidden'));

  pressKey(window, document, 'k', { metaKey: true });
  assert.equal(palette.hasAttribute('hidden'), false);

  pressKey(window, document, 'k', { metaKey: true });
  assert.equal(palette.hasAttribute('hidden'), true);

  // Ctrl+K works equivalently for non-Mac platforms.
  pressKey(window, document, 'K', { ctrlKey: true });
  assert.equal(palette.hasAttribute('hidden'), false);
});

import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';

const scriptSource = readFileSync(
  new URL('../../resources/dist/laradocs-ai.js', import.meta.url),
  'utf8',
);

const STRINGS = {
  thinking: 'Reading the documentation...',
  searching: 'Searching the documentation...',
  error: 'Something went wrong. Please try again.',
  rate_limited: 'Too many questions.',
  unauthorised: 'Not for you.',
  unavailable: 'Not available.',
};

/**
 * Build a fresh JSDOM around the widget markup the blade partial renders, eval
 * the bundled script in it, and return handles into the resulting window. Each
 * test gets its own isolated DOM so listeners and state never leak across
 * tests.
 */
async function bootWidget({ stream = '1', history = '10', version = null, token = 'csrf-token' } = {}) {
  const attrs = [
    'data-laradocs-ai',
    'data-laradocs-ai-url="/docs/_laradocs/ai/chat"',
    `data-laradocs-ai-stream="${stream}"`,
    `data-laradocs-ai-history="${history}"`,
    'data-laradocs-ai-max="2000"',
    `data-laradocs-ai-strings='${JSON.stringify(STRINGS)}'`,
    'data-position="right"',
  ];

  if (version) attrs.push(`data-laradocs-ai-version="${version}"`);
  if (token) attrs.push(`data-laradocs-ai-token="${token}"`);

  const dom = new JSDOM(
    `<!doctype html><html><body>
      <div class="laradocs-ai" ${attrs.join(' ')}>
        <button type="button" data-laradocs-ai-open>Ask the docs</button>
        <div class="laradocs-ai-panel" data-laradocs-ai-panel hidden>
          <button type="button" data-laradocs-ai-reset></button>
          <button type="button" data-laradocs-ai-close></button>
          <div class="laradocs-ai-thread" data-laradocs-ai-thread>
            <div class="laradocs-ai-message is-assistant"><div class="laradocs-ai-bubble">Greeting</div></div>
          </div>
          <form data-laradocs-ai-form>
            <textarea data-laradocs-ai-input rows="1"></textarea>
            <button type="submit" data-laradocs-ai-send></button>
          </form>
        </div>
      </div>
    </body></html>`,
    { runScripts: 'outside-only', pretendToBeVisual: true, url: 'https://docs.test/docs/install' },
  );

  const { window } = dom;

  window.eval(scriptSource);

  if (window.document.readyState === 'loading') {
    await new Promise((resolve) => {
      window.document.addEventListener('DOMContentLoaded', resolve, { once: true });
    });
  }

  const q = (selector) => window.document.querySelector(selector);

  return {
    window,
    root: q('[data-laradocs-ai]'),
    panel: q('[data-laradocs-ai-panel]'),
    thread: q('[data-laradocs-ai-thread]'),
    form: q('[data-laradocs-ai-form]'),
    input: q('[data-laradocs-ai-input]'),
    open: q('[data-laradocs-ai-open]'),
    close: q('[data-laradocs-ai-close]'),
    reset: q('[data-laradocs-ai-reset]'),
  };
}

function tick(window, ms = 0) {
  return new Promise((resolve) => window.setTimeout(resolve, ms));
}

/** A fetch that answers with one JSON body, recording what it was sent. */
function jsonFetch(window, body, status = 200) {
  const calls = [];

  window.fetch = (url, options) => {
    calls.push({ url, options, payload: JSON.parse(options.body) });

    return Promise.resolve({
      ok: status >= 200 && status < 300,
      status,
      headers: { get: () => 'application/json' },
      json: () => Promise.resolve(body),
    });
  };

  return calls;
}

/** A fetch that answers with a server-sent event stream, one chunk at a time. */
function streamFetch(window, frames) {
  const encoder = new TextEncoder();
  let index = 0;

  window.fetch = () => Promise.resolve({
    ok: true,
    status: 200,
    headers: { get: () => 'text/event-stream; charset=utf-8' },
    body: {
      getReader: () => ({
        read: () => Promise.resolve(index < frames.length
          ? { done: false, value: encoder.encode(frames[index++]) }
          : { done: true, value: undefined }),
      }),
    },
  });
}

function sseFrame(event) {
  return `data: ${JSON.stringify(event)}\n\n`;
}

function ask(window, form, input, question) {
  input.value = question;
  form.dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
}

function bubbles(thread) {
  return [...thread.querySelectorAll('.laradocs-ai-message')].map((message) => ({
    role: message.className.replace('laradocs-ai-message is-', ''),
    html: message.querySelector('.laradocs-ai-bubble')?.innerHTML ?? '',
    text: message.textContent.trim(),
  }));
}

test('the launcher opens and closes the panel', async () => {
  const { window, root, panel, open, close } = await bootWidget();

  assert.equal(panel.hidden, true);

  open.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
  assert.equal(panel.hidden, false);
  assert.ok(root.classList.contains('is-open'));

  close.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
  assert.equal(panel.hidden, true);
  assert.ok(!root.classList.contains('is-open'));
});

test('escape closes an open panel', async () => {
  const { window, panel, open } = await bootWidget();

  open.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
  window.document.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));

  assert.equal(panel.hidden, true);
});

test('a question and its answer land in the thread', async () => {
  const { window, thread, form, input } = await bootWidget({ stream: '0' });
  const calls = jsonFetch(window, { answer: 'Run **composer require**.' });

  ask(window, form, input, 'How do I install?');
  await tick(window, 10);

  const rendered = bubbles(thread);

  assert.equal(rendered[1].text, 'How do I install?');
  assert.equal(rendered[1].role, 'user');
  assert.equal(rendered[2].html, '<p>Run <strong>composer require</strong>.</p>');
  assert.equal(calls[0].payload.message, 'How do I install?');
  assert.equal(calls[0].payload.stream, false);
  assert.equal(calls[0].options.headers['X-CSRF-TOKEN'], 'csrf-token');
  assert.equal(input.value, '');
});

test('enter sends and shift+enter does not', async () => {
  const { window, thread, form, input } = await bootWidget({ stream: '0' });
  jsonFetch(window, { answer: 'Answered.' });

  input.value = 'first line';
  input.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Enter', shiftKey: true, bubbles: true, cancelable: true }));
  await tick(window, 10);
  assert.equal(bubbles(thread).length, 1, 'shift+enter must not send');

  input.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }));
  await tick(window, 10);
  assert.equal(bubbles(thread).length, 3);

  assert.ok(form);
});

test('an empty question is not sent', async () => {
  const { window, thread, form, input } = await bootWidget({ stream: '0' });
  const calls = jsonFetch(window, { answer: 'Answered.' });

  ask(window, form, input, '   ');
  await tick(window, 10);

  assert.equal(calls.length, 0);
  assert.equal(bubbles(thread).length, 1);
});

test('the thread is replayed on the next question, trimmed to the limit', async () => {
  const { window, form, input } = await bootWidget({ stream: '0', history: '2' });
  const calls = jsonFetch(window, { answer: 'Answered.' });

  ask(window, form, input, 'first');
  await tick(window, 10);
  ask(window, form, input, 'second');
  await tick(window, 10);
  ask(window, form, input, 'third');
  await tick(window, 10);

  assert.deepEqual(calls[0].payload.history, []);
  assert.deepEqual(calls[1].payload.history, [
    { role: 'user', content: 'first' },
    { role: 'assistant', content: 'Answered.' },
  ]);
  assert.deepEqual(calls[2].payload.history, [
    { role: 'user', content: 'second' },
    { role: 'assistant', content: 'Answered.' },
  ]);
});

test('history is left out entirely when the limit is zero', async () => {
  const { window, form, input } = await bootWidget({ stream: '0', history: '0' });
  const calls = jsonFetch(window, { answer: 'Answered.' });

  ask(window, form, input, 'first');
  await tick(window, 10);
  ask(window, form, input, 'second');
  await tick(window, 10);

  assert.deepEqual(calls[1].payload.history, []);
});

test('resetting clears the thread and what gets replayed', async () => {
  const { window, thread, form, input, reset } = await bootWidget({ stream: '0' });
  const calls = jsonFetch(window, { answer: 'Answered.' });

  ask(window, form, input, 'first');
  await tick(window, 10);

  reset.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));

  assert.equal(bubbles(thread).length, 1);
  assert.equal(bubbles(thread)[0].text, 'Greeting');

  ask(window, form, input, 'second');
  await tick(window, 10);

  assert.deepEqual(calls[1].payload.history, []);
});

test('the version the reader is on travels with the question', async () => {
  const { window, form, input } = await bootWidget({ stream: '0', version: 'v2' });
  const calls = jsonFetch(window, { answer: 'Answered.' });

  ask(window, form, input, 'How do I install?');
  await tick(window, 10);

  assert.equal(calls[0].payload.version, 'v2');
});

test('a streamed answer is assembled from its deltas', async () => {
  const { window, thread, form, input } = await bootWidget();

  streamFetch(window, [
    sseFrame({ type: 'stream_start' }),
    sseFrame({ type: 'tool_call', toolCall: { name: 'search_docs' } }),
    sseFrame({ type: 'text_delta', delta: 'Run ' }),
    sseFrame({ type: 'text_delta', delta: '`composer require`' }),
    sseFrame({ type: 'text_delta', delta: ' to install.' }),
    sseFrame({ type: 'stream_end', reason: 'stop' }) + 'data: [DONE]\n\n',
  ]);

  ask(window, form, input, 'How do I install?');
  await tick(window, 30);

  const rendered = bubbles(thread);

  assert.equal(rendered[2].html, '<p>Run <code>composer require</code> to install.</p>');
});

test('a stream split mid-frame still parses', async () => {
  const { window, thread, form, input } = await bootWidget();
  const frame = sseFrame({ type: 'text_delta', delta: 'Split answer.' });

  streamFetch(window, [frame.slice(0, 12), frame.slice(12) + 'data: [DONE]\n\n']);

  ask(window, form, input, 'How do I install?');
  await tick(window, 30);

  assert.equal(bubbles(thread)[2].html, '<p>Split answer.</p>');
});

test('a stream that only errors shows the error it carried', async () => {
  const { window, thread, form, input } = await bootWidget();

  streamFetch(window, [
    sseFrame({ type: 'error', message: 'The provider is down.' }),
    'data: [DONE]\n\n',
  ]);

  ask(window, form, input, 'How do I install?');
  await tick(window, 30);

  const rendered = bubbles(thread);

  assert.equal(rendered[2].role, 'error');
  assert.equal(rendered[2].text, 'The provider is down.');
});

test('a stream that says nothing at all shows the generic error', async () => {
  const { window, thread, form, input } = await bootWidget();

  streamFetch(window, ['data: [DONE]\n\n']);

  ask(window, form, input, 'How do I install?');
  await tick(window, 30);

  assert.equal(bubbles(thread)[2].text, STRINGS.error);
});

test('http failures are reported in the reader own language', async () => {
  const cases = [
    [401, STRINGS.unauthorised],
    [403, STRINGS.unauthorised],
    [404, STRINGS.unavailable],
    [429, STRINGS.rate_limited],
    [500, STRINGS.error],
  ];

  for (const [status, expected] of cases) {
    const { window, thread, form, input } = await bootWidget({ stream: '0' });
    jsonFetch(window, {}, status);

    ask(window, form, input, 'How do I install?');
    await tick(window, 10);

    assert.equal(bubbles(thread)[2].text, expected, `status ${status}`);
  }
});

test('a network failure is reported rather than swallowed', async () => {
  const { window, thread, form, input } = await bootWidget({ stream: '0' });
  window.fetch = () => Promise.reject(new Error('offline'));

  ask(window, form, input, 'How do I install?');
  await tick(window, 10);

  assert.equal(bubbles(thread)[2].text, STRINGS.error);
});

test('a json body without an answer falls back to its message', async () => {
  const { window, thread, form, input } = await bootWidget({ stream: '0' });
  jsonFetch(window, { error: 'Assistant unavailable', message: 'Try again shortly.' });

  ask(window, form, input, 'How do I install?');
  await tick(window, 10);

  assert.equal(bubbles(thread)[2].text, 'Try again shortly.');
});

test('the composer is locked while an answer is in flight', async () => {
  const { window, form, input } = await bootWidget({ stream: '0' });
  const send = window.document.querySelector('[data-laradocs-ai-send]');

  let release;
  window.fetch = () => new Promise((resolve) => {
    release = () => resolve({
      ok: true,
      status: 200,
      headers: { get: () => 'application/json' },
      json: () => Promise.resolve({ answer: 'Answered.' }),
    });
  });

  ask(window, form, input, 'How do I install?');
  await tick(window, 10);

  assert.equal(send.disabled, true);
  assert.equal(input.disabled, true);

  release();
  await tick(window, 10);

  assert.equal(send.disabled, false);
  assert.equal(input.disabled, false);
});

test('markdown is rendered as a safe subset', async () => {
  const { window, thread, form, input } = await bootWidget({ stream: '0' });

  jsonFetch(window, {
    answer: [
      'See <script>alert(1)</script> and [the guide](/docs/guide).',
      '',
      '- one',
      '- two',
      '',
      '1. first',
      '2. second',
      '',
      '```php',
      'echo "**not bold**";',
      '```',
      '',
      'A [bad link](javascript:alert(1)) and an *aside* and [outside](https://example.com/x).',
    ].join('\n'),
  });

  ask(window, form, input, 'Show me everything.');
  await tick(window, 10);

  const html = bubbles(thread)[2].html;

  assert.ok(!html.includes('<script>'), 'script tags must be escaped');
  assert.ok(html.includes('&lt;script&gt;'));
  assert.ok(html.includes('<a href="/docs/guide">the guide</a>'));
  assert.ok(html.includes('<ul><li>one</li><li>two</li></ul>'));
  assert.ok(html.includes('<ol><li>first</li><li>second</li></ol>'));
  assert.ok(html.includes('<pre><code class="language-php">echo "**not bold**";</code></pre>'));
  assert.ok(!html.includes('javascript:'), 'javascript: URLs must be dropped');
  assert.ok(html.includes('bad link'), 'the label survives a dropped link');
  assert.ok(html.includes('<em>aside</em>'));
  assert.ok(html.includes('rel="noopener noreferrer"'), 'external links open safely');
});

test('unreadable widget strings fall back to the built-in ones', async () => {
  const dom = new JSDOM(
    `<!doctype html><html><body>
      <div data-laradocs-ai data-laradocs-ai-url="/chat" data-laradocs-ai-stream="0"
           data-laradocs-ai-strings="{not json">
        <div data-laradocs-ai-panel hidden>
          <div data-laradocs-ai-thread></div>
          <form data-laradocs-ai-form>
            <textarea data-laradocs-ai-input></textarea>
            <button data-laradocs-ai-send></button>
          </form>
        </div>
      </div>
    </body></html>`,
    { runScripts: 'outside-only', pretendToBeVisual: true },
  );

  const { window } = dom;
  window.eval(scriptSource);

  if (window.document.readyState === 'loading') {
    await new Promise((resolve) => {
      window.document.addEventListener('DOMContentLoaded', resolve, { once: true });
    });
  }

  window.fetch = () => Promise.reject(new Error('offline'));

  const form = window.document.querySelector('[data-laradocs-ai-form]');
  const input = window.document.querySelector('[data-laradocs-ai-input]');
  ask(window, form, input, 'anything');
  await tick(window, 10);

  const thread = window.document.querySelector('[data-laradocs-ai-thread]');

  assert.equal(bubbles(thread)[1].text, 'Something went wrong. Please try again.');
});

test('the script does nothing on a page without the widget', async () => {
  const dom = new JSDOM('<!doctype html><html><body><p>Nothing here.</p></body></html>', {
    runScripts: 'outside-only',
    pretendToBeVisual: true,
  });

  dom.window.eval(scriptSource);

  if (dom.window.document.readyState === 'loading') {
    await new Promise((resolve) => {
      dom.window.document.addEventListener('DOMContentLoaded', resolve, { once: true });
    });
  }

  assert.equal(dom.window.document.body.innerHTML, '<p>Nothing here.</p>');
});

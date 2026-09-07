/* Laradocs AI chat widget.
 *
 * Hand-authored, no build step, matching resources/dist/laradocs.js. It lives
 * in its own file because the widget is droppable into pages this package does
 * not own, where the docs script has no business running.
 *
 * The transcript lives here and nowhere else: every request replays the turns
 * it wants the assistant to remember, so nothing is stored server side and
 * "start a new conversation" is genuinely a new conversation.
 */
(function () {
  'use strict';

  var DEFAULTS = {
    thinking: 'Reading the documentation...',
    searching: 'Searching the documentation...',
    error: 'Something went wrong. Please try again.',
    rate_limited: 'Please wait a moment and try again.',
    unauthorised: 'You are not allowed to use the documentation assistant.',
    unavailable: 'The documentation assistant is not available right now.'
  };

  function escapeHtml(text) {
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  // Only http(s), root-relative and in-page targets survive, so a link the
  // model invents cannot become a javascript: or data: URL.
  function safeUrl(url) {
    var trimmed = String(url).trim();
    return /^(https?:\/\/|\/|#|\.\/|\.\.\/)/i.test(trimmed) ? trimmed : null;
  }

  /* A deliberately small markdown subset: fenced and inline code, links,
   * bold, italics, bullet and numbered lists, and paragraphs. Everything is
   * escaped before any of it is applied, so nothing the model writes can
   * introduce markup of its own. Code spans are lifted out first and put back
   * last, so markdown inside a code sample stays a code sample. */
  function renderMarkdown(source) {
    var blocks = [];
    var text = escapeHtml(source);

    function hold(html) {
      blocks.push(html);
      return '%%LDAI' + (blocks.length - 1) + '%%';
    }

    text = text.replace(/```([\w+-]*)\n([\s\S]*?)```/g, function (all, lang, code) {
      return hold('<pre><code' + (lang ? ' class="language-' + lang + '"' : '') + '>' + code.replace(/\n$/, '') + '</code></pre>');
    });

    text = text.replace(/`([^`\n]+)`/g, function (all, code) {
      return hold('<code>' + code + '</code>');
    });

    text = text.replace(/\[([^\]\n]+)\]\(([^)\s]+)\)/g, function (all, label, url) {
      var href = safeUrl(url.replace(/&quot;/g, '"'));
      if (!href) return label;
      var external = /^https?:\/\//i.test(href);
      return '<a href="' + escapeHtml(href) + '"' + (external ? ' target="_blank" rel="noopener noreferrer"' : '') + '>' + label + '</a>';
    });

    text = text.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
    text = text.replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>');

    var html = text.split(/\n{2,}/).map(function (chunk) {
      var lines = chunk.split('\n');
      var bulleted = lines.every(function (line) { return /^\s*[-*]\s+/.test(line); });
      var numbered = lines.every(function (line) { return /^\s*\d+[.)]\s+/.test(line); });

      if (bulleted || numbered) {
        var tag = bulleted ? 'ul' : 'ol';
        return '<' + tag + '>' + lines.map(function (line) {
          return '<li>' + line.replace(/^\s*(?:[-*]|\d+[.)])\s+/, '') + '</li>';
        }).join('') + '</' + tag + '>';
      }

      return '<p>' + lines.join('<br>') + '</p>';
    }).join('');

    return html.replace(/%%LDAI(\d+)%%/g, function (all, index) {
      return blocks[Number(index)];
    });
  }

  function parseStrings(element) {
    var strings = {};
    var key;

    for (key in DEFAULTS) {
      if (Object.prototype.hasOwnProperty.call(DEFAULTS, key)) strings[key] = DEFAULTS[key];
    }

    try {
      var supplied = JSON.parse(element.getAttribute('data-laradocs-ai-strings') || '{}');
      for (key in supplied) {
        if (Object.prototype.hasOwnProperty.call(supplied, key) && supplied[key]) strings[key] = supplied[key];
      }
    } catch (e) {}

    return strings;
  }

  function initAiChat() {
    var root = document.querySelector('[data-laradocs-ai]');
    if (!root || root.hasAttribute('data-laradocs-ai-ready')) return;

    var panel = root.querySelector('[data-laradocs-ai-panel]');
    var thread = root.querySelector('[data-laradocs-ai-thread]');
    var form = root.querySelector('[data-laradocs-ai-form]');
    var input = root.querySelector('[data-laradocs-ai-input]');
    var send = root.querySelector('[data-laradocs-ai-send]');
    if (!panel || !thread || !form || !input || !send) return;

    root.setAttribute('data-laradocs-ai-ready', '1');

    var strings = parseStrings(root);
    var endpoint = root.getAttribute('data-laradocs-ai-url');
    var token = root.getAttribute('data-laradocs-ai-token');
    var version = root.getAttribute('data-laradocs-ai-version');
    var historyLimit = parseInt(root.getAttribute('data-laradocs-ai-history'), 10) || 0;
    var wantsStream = root.getAttribute('data-laradocs-ai-stream') === '1';
    var greeting = thread.innerHTML;
    var messages = [];
    var busy = false;

    function scrollToEnd() {
      thread.scrollTop = thread.scrollHeight;
    }

    function bubble(role) {
      var wrapper = document.createElement('div');
      wrapper.className = 'laradocs-ai-message is-' + role;

      var body = document.createElement('div');
      body.className = 'laradocs-ai-bubble';
      wrapper.appendChild(body);
      thread.appendChild(wrapper);
      scrollToEnd();

      return body;
    }

    function status(label) {
      var wrapper = document.createElement('div');
      wrapper.className = 'laradocs-ai-message is-assistant';
      wrapper.innerHTML = '<div class="laradocs-ai-status"><span class="laradocs-ai-dots"><i></i><i></i><i></i></span><span></span></div>';
      wrapper.querySelector('span:last-child').textContent = label;
      thread.appendChild(wrapper);
      scrollToEnd();

      return wrapper;
    }

    function drop(element) {
      if (element && element.parentNode) element.parentNode.removeChild(element);
    }

    function fail(message) {
      bubble('error').textContent = message;
    }

    function setBusy(value) {
      busy = value;
      send.disabled = value;
      input.disabled = value;
    }

    function grow() {
      input.style.height = 'auto';
      input.style.height = Math.min(input.scrollHeight, 128) + 'px';
    }

    function open() {
      panel.hidden = false;
      root.classList.add('is-open');
      input.focus();
      scrollToEnd();
    }

    function close() {
      panel.hidden = true;
      root.classList.remove('is-open');
    }

    function reset() {
      messages = [];
      thread.innerHTML = greeting;
      input.value = '';
      grow();
      input.focus();
    }

    function payload(question, streaming) {
      var body = {
        message: question,
        history: historyLimit > 0 ? messages.slice(-historyLimit) : [],
        stream: streaming
      };

      if (version) body.version = version;

      return body;
    }

    function headers() {
      var value = {
        'Content-Type': 'application/json',
        Accept: 'text/event-stream, application/json',
        'X-Requested-With': 'XMLHttpRequest'
      };

      if (token) value['X-CSRF-TOKEN'] = token;

      return value;
    }

    function messageForStatus(code) {
      if (code === 401 || code === 403) return strings.unauthorised;
      if (code === 404) return strings.unavailable;
      if (code === 429) return strings.rate_limited;

      return strings.error;
    }

    function remember(question, answer) {
      messages.push({ role: 'user', content: question });
      if (answer) messages.push({ role: 'assistant', content: answer });
    }

    function apply(event, view) {
      if (event.type === 'text_delta' && typeof event.delta === 'string') {
        view.answer += event.delta;
        view.body.innerHTML = renderMarkdown(view.answer);
        scrollToEnd();

        return;
      }

      if (event.type === 'tool_call') {
        view.label.textContent = strings.searching;

        return;
      }

      if (typeof event.type === 'string' && event.type.indexOf('error') !== -1) {
        view.failed = typeof event.message === 'string' && event.message !== ''
          ? event.message
          : strings.error;
      }
    }

    function readStream(response, question) {
      var indicator = status(strings.thinking);
      var view = {
        body: null,
        label: indicator.querySelector('span:last-child'),
        answer: '',
        failed: null
      };

      var reader = response.body.getReader();
      var decoder = new TextDecoder();
      var buffer = '';

      // The bubble only replaces the thinking indicator once there is
      // something to put in it, so a run that spends its first seconds
      // calling tools does not show an empty box.
      function ensureBody() {
        if (view.body) return;
        drop(indicator);
        view.body = bubble('assistant');
      }

      function consume(line) {
        var data = line.replace(/^data:\s?/, '');
        if (data === '[DONE]' || data === '') return;

        var event;

        try {
          event = JSON.parse(data);
        } catch (e) {
          return;
        }

        if (event.type === 'text_delta') ensureBody();

        apply(event, view);
      }

      function pump() {
        return reader.read().then(function (result) {
          buffer += decoder.decode(result.value || new Uint8Array(), { stream: !result.done });

          var frames = buffer.split('\n\n');
          buffer = result.done ? '' : frames.pop();

          frames.forEach(function (frame) {
            frame.split('\n').forEach(consume);
          });

          if (!result.done) return pump();

          if (view.failed || view.answer === '') {
            drop(indicator);
            drop(view.body ? view.body.parentNode : null);
            fail(view.failed || strings.error);

            return;
          }

          remember(question, view.answer);
        });
      }

      return pump().catch(function () {
        drop(indicator);

        if (view.answer !== '') {
          remember(question, view.answer);

          return;
        }

        fail(strings.error);
      });
    }

    function readJson(response, question) {
      var indicator = status(strings.thinking);

      return response.json().then(function (data) {
        drop(indicator);

        if (!data || typeof data.answer !== 'string' || data.answer === '') {
          fail((data && data.message) || strings.error);

          return;
        }

        bubble('assistant').innerHTML = renderMarkdown(data.answer);
        remember(question, data.answer);
        scrollToEnd();
      }, function () {
        drop(indicator);
        fail(strings.error);
      });
    }

    function ask(question) {
      setBusy(true);
      bubble('user').textContent = question;

      var streaming = wantsStream && typeof TextDecoder !== 'undefined';

      fetch(endpoint, {
        method: 'POST',
        headers: headers(),
        credentials: 'same-origin',
        body: JSON.stringify(payload(question, streaming))
      }).then(function (response) {
        if (!response.ok) {
          fail(messageForStatus(response.status));

          return null;
        }

        var contentType = response.headers.get('Content-Type') || '';

        return contentType.indexOf('text/event-stream') !== -1 && response.body && response.body.getReader
          ? readStream(response, question)
          : readJson(response, question);
      }, function () {
        fail(strings.error);
      }).then(function () {
        setBusy(false);
        input.focus();
      });
    }

    function submit() {
      var question = input.value.trim();
      if (busy || question === '') return;

      input.value = '';
      grow();
      ask(question);
    }

    var opener = root.querySelector('[data-laradocs-ai-open]');
    var closer = root.querySelector('[data-laradocs-ai-close]');
    var resetter = root.querySelector('[data-laradocs-ai-reset]');

    if (opener) opener.addEventListener('click', open);
    if (closer) closer.addEventListener('click', close);
    if (resetter) resetter.addEventListener('click', reset);

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      submit();
    });

    input.addEventListener('input', grow);
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        submit();
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !panel.hidden) close();
    });
  }

  if (document.readyState !== 'loading') {
    initAiChat();
  } else {
    document.addEventListener('DOMContentLoaded', initAiChat);
  }
})();

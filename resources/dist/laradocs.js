(function () {
  'use strict';

  var STORAGE_KEY = 'laradocs-theme';

  function applyTheme(theme) {
    var root = document.documentElement;
    if (theme === 'light' || theme === 'dark') {
      root.setAttribute('data-theme', theme);
    } else {
      root.removeAttribute('data-theme');
    }
  }

  function currentTheme() {
    try { return localStorage.getItem(STORAGE_KEY) || 'auto'; }
    catch (e) { return 'auto'; }
  }

  function initTheme() {
    applyTheme(currentTheme());
    var toggle = document.querySelector('[data-laradocs-theme-toggle]');
    if (!toggle) return;
    toggle.setAttribute('data-theme-state', currentTheme());
    toggle.addEventListener('click', function () {
      var order = ['auto', 'light', 'dark'];
      var next = order[(order.indexOf(currentTheme()) + 1) % order.length];
      try { localStorage.setItem(STORAGE_KEY, next); } catch (e) {}
      applyTheme(next);
      toggle.setAttribute('data-theme-state', next);
    });
  }

  function initCopy() {
    document.querySelectorAll('.laradocs-code-copy').forEach(function (button) {
      var original = button.textContent;
      button.addEventListener('click', function () {
        var block = button.closest('.laradocs-code');
        var code = block ? block.querySelector('pre') : null;
        if (!code) return;
        navigator.clipboard.writeText(code.innerText).then(function () {
          button.textContent = 'Copied';
          setTimeout(function () { button.textContent = original; }, 1500);
        });
      });
    });
  }

  function initMobileNav() {
    var shell = document.querySelector('.laradocs-shell');
    var button = document.querySelector('[data-laradocs-menu]');
    var backdrop = document.querySelector('[data-laradocs-backdrop]');
    if (!shell || !button) return;
    function close() { shell.classList.remove('nav-open'); }
    function toggle() { shell.classList.toggle('nav-open'); }
    button.addEventListener('click', toggle);
    if (backdrop) backdrop.addEventListener('click', close);
    document.querySelectorAll('.laradocs-sidebar a').forEach(function (link) {
      link.addEventListener('click', close);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') close();
    });
  }

  function initScrollSpy() {
    var links = Array.prototype.slice.call(document.querySelectorAll('.laradocs-toc a'));
    if (!links.length) return;
    var byId = {};
    var ids = [];
    links.forEach(function (link) {
      var id = link.getAttribute('href').slice(1);
      byId[id] = link;
      ids.push(id);
    });
    var triggerOffset = 140;

    function update() {
      var doc = document.documentElement;
      var scrollY = window.scrollY;
      var maxScroll = Math.max(1, doc.scrollHeight - window.innerHeight);

      var heads = [];
      for (var i = 0; i < ids.length; i++) {
        var el = document.getElementById(ids[i]);
        if (!el) continue;
        heads.push({ id: ids[i], y: el.getBoundingClientRect().top + scrollY });
      }
      if (!heads.length) return;

      var activations = heads.map(function (h) { return Math.max(0, h.y - triggerOffset); });

      // Headings whose natural activation point is past maxScroll can never highlight.
      // Redistribute them evenly across the remaining scroll range so each owns a slice.
      var firstUnreachable = -1;
      for (var k = 0; k < activations.length; k++) {
        if (activations[k] > maxScroll) { firstUnreachable = k; break; }
      }
      if (firstUnreachable >= 0) {
        var start = firstUnreachable === 0 ? 0 : activations[firstUnreachable - 1];
        var n = heads.length - firstUnreachable;
        var step = Math.max(1, (maxScroll - start) / n);
        for (var j = 0; j < n; j++) {
          activations[firstUnreachable + j] = start + step * (j + 1);
        }
      }

      var active = null;
      for (var m = 0; m < heads.length; m++) {
        if (activations[m] <= scrollY) active = heads[m].id;
        else break;
      }

      links.forEach(function (l) { l.classList.remove('is-active'); });
      if (active && byId[active]) byId[active].classList.add('is-active');
    }

    var raf = null;
    function schedule() {
      if (raf !== null) return;
      raf = requestAnimationFrame(function () { raf = null; update(); });
    }
    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule);
    update();
  }

  function initProgress() {
    var bar = document.querySelector('.laradocs-progress');
    if (!bar) return;
    var rafId = null;
    function update() {
      var doc = document.documentElement;
      var scrolled = doc.scrollTop || document.body.scrollTop;
      var max = (doc.scrollHeight || document.body.scrollHeight) - doc.clientHeight;
      var pct = max > 0 ? Math.min(100, Math.max(0, (scrolled / max) * 100)) : 0;
      bar.style.setProperty('--dc-progress', pct + '%');
      rafId = null;
    }
    function schedule() {
      if (rafId === null) rafId = requestAnimationFrame(update);
    }
    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule);
    update();
  }

  function initZoom() {
    document.querySelectorAll('img.laradocs-image').forEach(function (img) {
      img.addEventListener('click', function () {
        img.classList.toggle('is-zoomed');
      });
    });
  }

  function initSidebarIndex() {
    var items = document.querySelectorAll('.laradocs-sidebar li');
    items.forEach(function (li, i) {
      li.style.setProperty('--i', String(i));
    });
  }

  function initSearchShortcut() {
    var input = document.querySelector('[data-laradocs-search]');
    if (!input) return;
    document.addEventListener('keydown', function (e) {
      if (e.key === '/' && document.activeElement !== input && !/input|textarea/i.test((document.activeElement || {}).tagName || '')) {
        e.preventDefault();
        input.focus();
      }
    });
  }

  // Split a raw query into lowercased, non-empty terms for highlighting.
  function queryTerms(q) {
    return q.trim().toLowerCase().split(/\s+/).filter(function (t) { return t; });
  }

  function escapeRegExp(s) {
    return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

  // Return a DocumentFragment of `text` with every case-insensitive occurrence
  // of any term wrapped in <mark>. Built entirely from text nodes and created
  // elements — the source text is never parsed as HTML, so server-supplied
  // titles and excerpts can be highlighted without an injection risk.
  function highlight(text, terms) {
    var fragment = document.createDocumentFragment();
    text = text || '';
    var cleaned = (terms || []).filter(function (t) { return t; }).map(escapeRegExp);
    if (text === '' || !cleaned.length) {
      if (text !== '') fragment.appendChild(document.createTextNode(text));
      return fragment;
    }
    var re = new RegExp('(' + cleaned.join('|') + ')', 'ig');
    var lastIndex = 0;
    var match;
    while ((match = re.exec(text)) !== null) {
      if (match.index > lastIndex) {
        fragment.appendChild(document.createTextNode(text.slice(lastIndex, match.index)));
      }
      var mark = document.createElement('mark');
      mark.className = 'laradocs-palette-mark';
      mark.textContent = match[0];
      fragment.appendChild(mark);
      lastIndex = match.index + match[0].length;
    }
    if (lastIndex < text.length) {
      fragment.appendChild(document.createTextNode(text.slice(lastIndex)));
    }
    return fragment;
  }

  // A result's ancestor trail. Prefer the server-built breadcrumb; fall back to
  // the flat group, or to nothing, so older payloads still render sensibly.
  function crumbsOf(r) {
    if (Array.isArray(r.breadcrumb)) return r.breadcrumb;
    return r.group ? [r.group] : [];
  }
  function sectionOf(r) {
    return crumbsOf(r)[0] || '';
  }

  // Cluster results by their top-level section, preserving the rank order both
  // of sections (by first appearance) and of hits within each section.
  function groupBySection(list) {
    var buckets = new Map();
    list.forEach(function (r) {
      var section = sectionOf(r);
      if (!buckets.has(section)) buckets.set(section, []);
      buckets.get(section).push(r);
    });
    var out = [];
    buckets.forEach(function (items) { out = out.concat(items); });
    return out;
  }

  function initPalette() {
    var palette = document.querySelector('[data-laradocs-palette]');
    var input = document.querySelector('[data-laradocs-palette-input]');
    var results = document.querySelector('[data-laradocs-palette-results]');
    if (!palette || !input || !results) return;

    // When search is enabled the server exposes a full-text endpoint; without
    // it we degrade gracefully to substring matching on the pre-rendered titles.
    var searchUrl = palette.getAttribute('data-laradocs-search-url');
    var minChars = parseInt(palette.getAttribute('data-laradocs-search-min'), 10) || 1;
    var originalHtml = results.innerHTML;
    var activeIndex = -1;
    var debounce = null;
    var requestId = 0;

    function items() {
      return Array.prototype.slice.call(results.querySelectorAll('li a'));
    }
    function visibleItems() {
      return items().filter(function (a) { return !a.parentNode.hasAttribute('hidden'); });
    }
    function setActive(i) {
      var visible = visibleItems();
      if (!visible.length) { activeIndex = -1; return; }
      activeIndex = Math.max(0, Math.min(visible.length - 1, i));
      items().forEach(function (a) { a.classList.remove('is-active'); });
      visible[activeIndex].classList.add('is-active');
      visible[activeIndex].scrollIntoView({ block: 'nearest' });
    }
    function localFilter(q) {
      q = q.trim().toLowerCase();
      items().forEach(function (a) {
        var label = a.getAttribute('data-label') || '';
        a.parentNode.toggleAttribute('hidden', !(!q || label.indexOf(q) !== -1));
        a.classList.remove('is-active');
      });
      setActive(0);
    }
    function restore() {
      results.innerHTML = originalHtml;
    }
    function renderResults(list, terms) {
      results.innerHTML = '';
      if (!list.length) {
        var empty = document.createElement('li');
        empty.className = 'laradocs-palette-empty';
        empty.textContent = 'No results';
        results.appendChild(empty);
        activeIndex = -1;
        return;
      }
      var lastSection = null;
      groupBySection(list).forEach(function (r) {
        var crumbs = crumbsOf(r);
        var section = crumbs[0] || '';
        // Emit a header the first time each section's run begins. Headers are
        // header <li>s with no anchor, so keyboard navigation skips them.
        if (section !== lastSection) {
          lastSection = section;
          if (section !== '') {
            var header = document.createElement('li');
            header.className = 'laradocs-palette-section';
            header.setAttribute('aria-hidden', 'true');
            header.textContent = section;
            results.appendChild(header);
          }
        }
        var li = document.createElement('li');
        var a = document.createElement('a');
        a.href = r.url;
        a.setAttribute('data-label', (r.title || '').toLowerCase());
        var title = document.createElement('span');
        title.className = 'laradocs-palette-title';
        title.appendChild(highlight(r.title || '', terms));
        a.appendChild(title);
        // The deeper trail beneath the section header (if any) becomes the
        // per-item breadcrumb, so the section name isn't repeated on each row.
        var sub = crumbs.slice(1);
        if (sub.length) {
          var breadcrumb = document.createElement('span');
          breadcrumb.className = 'laradocs-palette-breadcrumb';
          breadcrumb.textContent = sub.join(' › ');
          a.appendChild(breadcrumb);
        }
        if (r.excerpt) {
          var excerpt = document.createElement('span');
          excerpt.className = 'laradocs-palette-excerpt';
          excerpt.appendChild(highlight(r.excerpt, terms));
          a.appendChild(excerpt);
        }
        li.appendChild(a);
        results.appendChild(li);
      });
      setActive(0);
    }
    function remoteSearch(q) {
      var id = ++requestId;
      fetch(searchUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (id !== requestId) return;
          renderResults((data && data.results) || [], queryTerms(q));
        })
        .catch(function () {
          if (id !== requestId) return;
          restore();
          localFilter(q);
        });
    }
    function onInput() {
      var q = input.value.trim();
      if (!searchUrl) { localFilter(q); return; }
      if (debounce) clearTimeout(debounce);
      if (q.length < minChars) {
        requestId++;
        restore();
        localFilter('');
        return;
      }
      debounce = setTimeout(function () { remoteSearch(q); }, 150);
    }

    function open() {
      palette.removeAttribute('hidden');
      input.value = '';
      restore();
      localFilter('');
      setTimeout(function () { input.focus(); }, 10);
    }
    function close() {
      palette.setAttribute('hidden', '');
      activeIndex = -1;
    }

    document.addEventListener('keydown', function (e) {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        if (palette.hasAttribute('hidden')) open(); else close();
        return;
      }
      if (palette.hasAttribute('hidden')) return;
      if (e.key === 'Escape') { e.preventDefault(); close(); }
      else if (e.key === 'ArrowDown') { e.preventDefault(); setActive(activeIndex + 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(activeIndex - 1); }
      else if (e.key === 'Enter' && document.activeElement === input) {
        var visible = visibleItems();
        if (visible[activeIndex]) { e.preventDefault(); window.location.href = visible[activeIndex].href; }
      }
    });

    document.querySelectorAll('[data-laradocs-palette-open]').forEach(function (btn) {
      btn.addEventListener('click', open);
    });
    document.querySelectorAll('[data-laradocs-palette-close]').forEach(function (el) {
      el.addEventListener('click', close);
    });
    input.addEventListener('input', onInput);
  }

  function boot() {
    initTheme();
    initCopy();
    initMobileNav();
    initScrollSpy();
    initProgress();
    initZoom();
    initSidebarIndex();
    initSearchShortcut();
    initPalette();
  }

  if (document.readyState !== 'loading') {
    boot();
  } else {
    document.addEventListener('DOMContentLoaded', boot);
  }
})();

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

  // Generic copy button: any element with [data-laradocs-copy] copies the
  // attribute's value (or its own text when the attribute is empty) and shows a
  // brief "copied" state. Used by the OpenAPI endpoint bars and server URLs.
  function initCopyText() {
    document.querySelectorAll('[data-laradocs-copy]').forEach(function (button) {
      button.addEventListener('click', function () {
        var value = button.getAttribute('data-laradocs-copy') || button.textContent || '';
        if (!value || !navigator.clipboard) return;
        navigator.clipboard.writeText(value.trim()).then(function () {
          button.classList.add('is-copied');
          setTimeout(function () { button.classList.remove('is-copied'); }, 1500);
        });
      });
    });
  }

  // Bulk expand/collapse for OpenAPI schema trees and the overview resource
  // index: a toolbar button toggles the `open` state of every <details> inside
  // its nearest <section>.
  var OA_DETAILS = 'details.laradocs-openapi-property, details.laradocs-openapi-branch, details.laradocs-openapi-tag-section';
  function initSchemaToggle() {
    document.querySelectorAll('[data-laradocs-schema-toggle]').forEach(function (button) {
      button.addEventListener('click', function () {
        var scope = button.closest('section') || document;
        var open = button.getAttribute('data-laradocs-schema-toggle') === 'expand';
        scope.querySelectorAll(OA_DETAILS).forEach(function (node) { node.open = open; });
      });
    });
  }

  // When a TOC / anchor link points at a heading inside a collapsed OpenAPI
  // <details> (e.g. a resource section on the overview), open its ancestors so
  // the target is actually revealed rather than hidden behind a closed summary.
  function initOpenApiSections() {
    function openAncestors(target) {
      for (var el = target; el; el = el.parentElement) {
        if (el.tagName === 'DETAILS' && !el.open) el.open = true;
      }
    }
    function fromHash() {
      var id = location.hash ? decodeURIComponent(location.hash.slice(1)) : '';
      var target = id && document.getElementById(id);
      if (target) openAncestors(target);
    }
    document.addEventListener('click', function (e) {
      var link = e.target.closest && e.target.closest('a[href^="#"]');
      if (!link) return;
      var id = decodeURIComponent(link.getAttribute('href').slice(1));
      var target = id && document.getElementById(id);
      if (target) openAncestors(target);
    });
    window.addEventListener('hashchange', fromHash);
    fromHash();
  }

  // OpenAPI operation pages render a request/response code panel inline in the
  // body. On wide screens, dock it into the right rail (in place of the TOC);
  // on narrow screens leave it inline near the top of the content.
  function initOpenApiAside() {
    var aside = document.querySelector('[data-laradocs-openapi-aside]');
    if (!aside) return;
    var main = document.querySelector('.laradocs-main');
    if (!main) return;

    var home = document.createComment('laradocs-openapi-aside');
    aside.parentNode.insertBefore(home, aside);
    var toc = main.querySelector('.laradocs-toc');
    var wide = window.matchMedia('(min-width: 1181px)');

    function place() {
      if (wide.matches) {
        if (toc) toc.setAttribute('hidden', '');
        if (aside.parentNode !== main) main.appendChild(aside);
        aside.classList.add('is-docked');
      } else {
        if (toc) toc.removeAttribute('hidden');
        if (home.parentNode && aside.previousSibling !== home) home.parentNode.insertBefore(aside, home);
        aside.classList.remove('is-docked');
      }
      aside.hidden = false;
    }

    place();
    if (wide.addEventListener) wide.addEventListener('change', place);
    else if (wide.addListener) wide.addListener(place);
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

  function initSidebarCollapse() {
    var nav = document.querySelector('.laradocs-sidebar nav');
    if (!nav) return;

    var chevronPath = 'M6 9l6 6 6-6';
    function makeChevron() {
      var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      svg.setAttribute('viewBox', '0 0 24 24');
      svg.setAttribute('fill', 'none');
      svg.setAttribute('stroke', 'currentColor');
      svg.setAttribute('stroke-width', '2');
      svg.setAttribute('stroke-linecap', 'round');
      svg.setAttribute('stroke-linejoin', 'round');
      svg.setAttribute('class', 'laradocs-nav-toggle');
      svg.setAttribute('aria-hidden', 'true');
      var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
      path.setAttribute('d', chevronPath);
      svg.appendChild(path);
      return svg;
    }

    // Top-level nav group headers (direct children of nav)
    var topGroups = nav.querySelectorAll(':scope > .laradocs-nav-group');
    topGroups.forEach(function (group) {
      var ul = group.nextElementSibling;
      if (!ul || ul.tagName !== 'UL') return;

      var isActive = !!ul.querySelector('a.is-active');
      group.appendChild(makeChevron());
      group.setAttribute('role', 'button');
      group.setAttribute('tabindex', '0');
      group.setAttribute('aria-expanded', isActive ? 'true' : 'false');

      function toggle() {
        var expanded = group.getAttribute('aria-expanded') === 'true';
        group.setAttribute('aria-expanded', expanded ? 'false' : 'true');
      }
      group.addEventListener('click', toggle);
      group.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
      });
    });

    // Node-level children (.laradocs-children inside <li>)
    nav.querySelectorAll('li').forEach(function (li) {
      var children = li.querySelector(':scope > .laradocs-children');
      if (!children) return;

      var groupTrigger = li.querySelector(':scope > .laradocs-nav-group');
      var linkTrigger = li.querySelector(':scope > a');

      var isActive = !!children.querySelector('a.is-active') || (linkTrigger && linkTrigger.classList.contains('is-active'));
      if (!isActive) children.classList.add('is-collapsed');

      if (groupTrigger) {
        groupTrigger.appendChild(makeChevron());
        groupTrigger.setAttribute('role', 'button');
        groupTrigger.setAttribute('tabindex', '0');
        groupTrigger.setAttribute('aria-expanded', isActive ? 'true' : 'false');

        function toggleGroup() {
          var expanded = groupTrigger.getAttribute('aria-expanded') === 'true';
          groupTrigger.setAttribute('aria-expanded', expanded ? 'false' : 'true');
          children.classList.toggle('is-collapsed', expanded);
        }
        groupTrigger.addEventListener('click', toggleGroup);
        groupTrigger.addEventListener('keydown', function (e) {
          if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleGroup(); }
        });
      } else if (linkTrigger) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'laradocs-nav-children-toggle';
        btn.setAttribute('aria-label', 'Toggle submenu');
        btn.setAttribute('aria-expanded', isActive ? 'true' : 'false');
        btn.appendChild(makeChevron());

        var row = document.createElement('div');
        row.className = 'laradocs-nav-link-row';
        li.insertBefore(row, linkTrigger);
        row.appendChild(linkTrigger);
        row.appendChild(btn);

        btn.addEventListener('click', function () {
          var expanded = btn.getAttribute('aria-expanded') === 'true';
          btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
          children.classList.toggle('is-collapsed', expanded);
        });
      }
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

  // Parse a version handle (e.g. "v2.0", "2.0.1", "1") into [major, minor, patch].
  // A leading "v" and any pre-release/build suffix are ignored; missing parts are 0.
  function parseSemver(value) {
    var m = String(value == null ? '' : value).trim().match(/^v?(\d+)(?:\.(\d+))?(?:\.(\d+))?/);
    if (!m) return null;
    return [parseInt(m[1], 10), parseInt(m[2] || '0', 10), parseInt(m[3] || '0', 10)];
  }

  // Compare two version handles. Returns -1, 0 or 1; null operands sort last.
  function compareSemver(a, b) {
    var pa = parseSemver(a);
    var pb = parseSemver(b);
    if (!pa && !pb) return 0;
    if (!pa) return 1;
    if (!pb) return -1;
    for (var i = 0; i < 3; i++) {
      if (pa[i] !== pb[i]) return pa[i] < pb[i] ? -1 : 1;
    }
    return 0;
  }

  // Show or hide inline version blocks based on the active version. Each block
  // carries one of data-version-since / -until / -only and starts out hidden;
  // matching blocks have the `hidden` attribute removed on load.
  function initVersionBlocks() {
    var current = window.__laradocsVersion;
    if (current == null || current === '') return;
    document.querySelectorAll('.version-block[data-version-since]').forEach(function (el) {
      if (compareSemver(current, el.getAttribute('data-version-since')) >= 0) el.removeAttribute('hidden');
    });
    document.querySelectorAll('.version-block[data-version-until]').forEach(function (el) {
      if (compareSemver(current, el.getAttribute('data-version-until')) < 0) el.removeAttribute('hidden');
    });
    document.querySelectorAll('.version-block[data-version-only]').forEach(function (el) {
      var allowed = (el.getAttribute('data-version-only') || '').split(',').map(function (s) { return s.trim(); });
      var match = allowed.some(function (v) { return v !== '' && compareSemver(current, v) === 0; });
      if (match) el.removeAttribute('hidden');
    });
  }

  // Keep the outdated-version banner dismissed for the session. Dismissal is
  // stored per active version so switching versions re-shows the banner.
  function initTabs() {
    var groups = document.querySelectorAll('.laradocs-tab-group');
    if (!groups.length) return;

    var STORAGE_PREFIX = 'laradocs:tabs:';

    function stored(group) {
      try { return localStorage.getItem(STORAGE_PREFIX + group) || ''; } catch (e) { return ''; }
    }
    function store(group, label) {
      try { localStorage.setItem(STORAGE_PREFIX + group, label); } catch (e) {}
    }

    // Activate `tabEl` within its own group without syncing siblings (used by sync).
    function activateInGroup(tabEl, group) {
      var controls = tabEl.getAttribute('aria-controls');
      group.querySelectorAll('[role="tab"]').forEach(function (t) {
        var active = t === tabEl;
        t.setAttribute('aria-selected', active ? 'true' : 'false');
        t.classList.toggle('is-active', active);
        t.tabIndex = active ? 0 : -1;
      });
      group.querySelectorAll('[role="tabpanel"]').forEach(function (panel) {
        panel.toggleAttribute('hidden', panel.id !== controls);
      });
    }

    // Activate `tabEl`, sync same-group blocks on the page, and persist.
    function activateTab(tabEl) {
      var group = tabEl.closest('.laradocs-tab-group');
      if (!group) return;
      activateInGroup(tabEl, group);

      var label = tabEl.textContent.trim();
      var dataGroup = group.getAttribute('data-tabs-group');
      if (dataGroup) {
        store(dataGroup, label);
        // Sync other blocks that share the same group name.
        document.querySelectorAll('.laradocs-tab-group[data-tabs-group="' + dataGroup + '"]').forEach(function (other) {
          if (other === group) return;
          var match = null;
          other.querySelectorAll('[role="tab"]').forEach(function (t) {
            if (t.textContent.trim() === label) match = t;
          });
          if (match) activateInGroup(match, other);
        });
      }
    }

    // Restore persisted selection for each group on page load.
    groups.forEach(function (group) {
      var dataGroup = group.getAttribute('data-tabs-group');
      if (!dataGroup) return;
      var label = stored(dataGroup);
      if (!label) return;
      var match = null;
      group.querySelectorAll('[role="tab"]').forEach(function (t) {
        if (t.textContent.trim() === label) match = t;
      });
      if (match) activateInGroup(match, group);
    });

    // Event listeners.
    groups.forEach(function (group) {
      var tablist = group.querySelector('[role="tablist"]');
      if (!tablist) return;

      tablist.addEventListener('click', function (e) {
        var tab = e.target.closest('[role="tab"]');
        if (tab) activateTab(tab);
      });

      tablist.addEventListener('keydown', function (e) {
        var tabs = Array.prototype.slice.call(tablist.querySelectorAll('[role="tab"]'));
        var idx = tabs.indexOf(document.activeElement);
        if (idx === -1) return;
        var next = null;
        if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
          e.preventDefault(); next = tabs[(idx + 1) % tabs.length];
        } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
          e.preventDefault(); next = tabs[(idx - 1 + tabs.length) % tabs.length];
        } else if (e.key === 'Home') {
          e.preventDefault(); next = tabs[0];
        } else if (e.key === 'End') {
          e.preventDefault(); next = tabs[tabs.length - 1];
        } else if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault(); activateTab(document.activeElement); return;
        }
        if (next) next.focus();
      });
    });
  }

  function initVersionBanner() {
    var banner = document.querySelector('[data-laradocs-outdated-banner]');
    if (!banner) return;
    var version = window.__laradocsVersion || '';
    var key = 'laradocs-banner-dismissed-' + version;
    try {
      if (sessionStorage.getItem(key) === '1') banner.hidden = true;
    } catch (e) {}
    var button = banner.querySelector('[data-laradocs-dismiss-version-banner]');
    if (!button) return;
    button.addEventListener('click', function () {
      banner.hidden = true;
      try { sessionStorage.setItem(key, '1'); } catch (e) {}
    });
  }

  function initSearchKbdHint() {
    var kbd = document.querySelector('[data-laradocs-kbd-trigger]');
    if (!kbd) return;
    try {
      var p = (navigator.userAgentData && navigator.userAgentData.platform) || navigator.platform || '';
      if (!/mac/i.test(p)) kbd.textContent = 'Ctrl+K';
    } catch (e) {}
  }

  function boot() {
    initTheme();
    initOpenApiAside();
    initCopy();
    initCopyText();
    initSchemaToggle();
    initOpenApiSections();
    initMobileNav();
    initScrollSpy();
    initProgress();
    initZoom();
    initSidebarIndex();
    initSidebarCollapse();
    initSearchShortcut();
    initPalette();
    initSearchKbdHint();
    initVersionBlocks();
    initVersionBanner();
    initTabs();
  }

  if (document.readyState !== 'loading') {
    boot();
  } else {
    document.addEventListener('DOMContentLoaded', boot);
  }
})();

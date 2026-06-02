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
    try {
      return localStorage.getItem(STORAGE_KEY) || 'auto';
    } catch (e) {
      return 'auto';
    }
  }

  function initTheme() {
    applyTheme(currentTheme());
    var toggle = document.querySelector('[data-laradocs-theme-toggle]');
    if (!toggle) return;
    toggle.addEventListener('click', function () {
      var order = ['auto', 'light', 'dark'];
      var next = order[(order.indexOf(currentTheme()) + 1) % order.length];
      try { localStorage.setItem(STORAGE_KEY, next); } catch (e) {}
      applyTheme(next);
      toggle.setAttribute('data-theme-state', next);
    });
    toggle.setAttribute('data-theme-state', currentTheme());
  }

  function initCopy() {
    document.querySelectorAll('.laradocs-code-copy').forEach(function (button) {
      button.addEventListener('click', function () {
        var block = button.closest('.laradocs-code');
        var code = block ? block.querySelector('pre') : null;
        if (!code) return;
        navigator.clipboard.writeText(code.innerText).then(function () {
          var original = button.textContent;
          button.textContent = 'Copied!';
          setTimeout(function () { button.textContent = original; }, 1500);
        });
      });
    });
  }

  function initMobileNav() {
    var shell = document.querySelector('.laradocs-shell');
    var button = document.querySelector('.laradocs-menu-btn');
    if (!shell || !button) return;
    button.addEventListener('click', function () {
      shell.classList.toggle('nav-open');
    });
    document.querySelectorAll('.laradocs-sidebar a').forEach(function (link) {
      link.addEventListener('click', function () { shell.classList.remove('nav-open'); });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') shell.classList.remove('nav-open');
    });
  }

  function initScrollSpy() {
    var links = Array.prototype.slice.call(document.querySelectorAll('.laradocs-toc a'));
    if (!links.length || !('IntersectionObserver' in window)) return;
    var byId = {};
    links.forEach(function (link) {
      var id = link.getAttribute('href').slice(1);
      byId[id] = link;
    });
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          links.forEach(function (l) { l.classList.remove('is-active'); });
          var active = byId[entry.target.id];
          if (active) active.classList.add('is-active');
        }
      });
    }, { rootMargin: '0px 0px -75% 0px' });
    Object.keys(byId).forEach(function (id) {
      var el = document.getElementById(id);
      if (el) observer.observe(el);
    });
  }

  function initZoom() {
    document.querySelectorAll('img[data-zoomable]').forEach(function (img) {
      img.addEventListener('click', function () {
        img.classList.toggle('is-zoomed');
      });
    });
  }

  function boot() {
    initTheme();
    initCopy();
    initMobileNav();
    initScrollSpy();
    initZoom();
  }

  if (document.readyState !== 'loading') {
    boot();
  } else {
    document.addEventListener('DOMContentLoaded', boot);
  }
})();

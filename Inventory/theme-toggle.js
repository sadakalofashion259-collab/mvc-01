/* =====================================================================
   SADA KALO — Theme Toggle (Light / Dark)
   Adds a persistent toggle pill to every .sk-appbar__right.
   Stores choice in localStorage, syncs across tabs.
   ===================================================================== */
(function () {
  'use strict';
  const STORAGE_KEY = 'sk-theme';

  function getSavedTheme() {
    try {
      const t = localStorage.getItem(STORAGE_KEY);
      if (t === 'light' || t === 'dark') return t;
    } catch (e) {}
    // default by OS preference, but lean light
    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
      return 'dark';
    }
    return 'light';
  }

  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    // update any meta theme-color
    let meta = document.querySelector('meta[name="theme-color"]');
    if (!meta) {
      meta = document.createElement('meta');
      meta.name = 'theme-color';
      document.head.appendChild(meta);
    }
    meta.setAttribute('content', theme === 'dark' ? '#09090b' : '#ffffff');
    // update all toggle pills currently on page
    document.querySelectorAll('[data-sk-theme-toggle]').forEach(updatePillUI);
  }

  function updatePillUI(pill) {
    const cur = document.documentElement.getAttribute('data-theme') || 'light';
    const next = cur === 'dark' ? 'light' : 'dark';
    pill.setAttribute('aria-label', 'Switch to ' + next + ' mode');
    pill.setAttribute('title', cur === 'dark' ? 'লাইট মোড' : 'ডার্ক মোড');
    pill.innerHTML =
      '<i class="fas ' + (cur === 'dark' ? 'fa-sun' : 'fa-moon') + '"></i>' +
      '<span class="sk-themepill__label">' + (cur === 'dark' ? 'LIGHT' : 'DARK') + '</span>';
  }

  function toggleTheme() {
    const cur = document.documentElement.getAttribute('data-theme') || 'light';
    const next = cur === 'dark' ? 'light' : 'dark';
    try { localStorage.setItem(STORAGE_KEY, next); } catch (e) {}
    applyTheme(next);
  }

  function injectPill() {
    const slots = document.querySelectorAll('.sk-appbar__right');
    slots.forEach(function (slot) {
      if (slot.querySelector('[data-sk-theme-toggle]')) return;
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'sk-themepill';
      btn.setAttribute('data-sk-theme-toggle', '');
      btn.addEventListener('click', toggleTheme);
      slot.insertBefore(btn, slot.firstChild);
      updatePillUI(btn);
    });
  }

  // Apply ASAP to prevent flash
  applyTheme(getSavedTheme());

  // Inject pill once DOM is parsed
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', injectPill);
  } else {
    injectPill();
  }

  // Sync across tabs
  window.addEventListener('storage', function (e) {
    if (e.key === STORAGE_KEY && (e.newValue === 'light' || e.newValue === 'dark')) {
      applyTheme(e.newValue);
    }
  });

  // expose
  window.skTheme = { toggle: toggleTheme, set: function (t) {
    if (t === 'light' || t === 'dark') {
      try { localStorage.setItem(STORAGE_KEY, t); } catch (e) {}
      applyTheme(t);
    }
  }, get: function () { return document.documentElement.getAttribute('data-theme') || 'light'; } };
})();

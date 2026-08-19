/* ============================================================
   সাদাকালো এন্টারপ্রাইজ — Application JS v2
   1. Theme controller  (light / dark, Bootstrap 5.3 native)
   2. InlineNotice API  (replaces SweetAlert for simple messages)
   3. DOM helper        (safe HTML escaping)
   Refactored: Security & Design — June 2026
   ============================================================ */

/* ── 1. THEME CONTROLLER ─────────────────────────────────── */
(function () {
  'use strict';

  var STORAGE_KEY = 'sadakalo-theme';

  /* ── Storage helpers (wrapped in try/catch for private-browse) */
  function getStoredTheme() {
    try { return localStorage.getItem(STORAGE_KEY); } catch (e) { return null; }
  }
  function storeTheme(theme) {
    try { localStorage.setItem(STORAGE_KEY, theme); } catch (e) { /* silently ignore */ }
  }
  function systemPreference() {
    return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)
      ? 'dark'
      : 'light';
  }

  /* ── Apply theme to <html> and update any icon/label nodes */
  function applyTheme(theme) {
    document.documentElement.setAttribute('data-bs-theme', theme);

    document.querySelectorAll('[data-theme-icon]').forEach(function (el) {
      el.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    });
    document.querySelectorAll('[data-theme-label]').forEach(function (el) {
      el.textContent = theme === 'dark' ? 'লাইট মোড' : 'ডার্ক মোড';
    });
  }

  /* ── Run immediately (before first paint) to avoid FOUC */
  applyTheme(getStoredTheme() || systemPreference());

  /* ── Public toggle called by header button */
  window.toggleTheme = function () {
    var current = document.documentElement.getAttribute('data-bs-theme') || 'light';
    var next    = current === 'dark' ? 'light' : 'dark';
    applyTheme(next);
    storeTheme(next);
  };

  /* ── Re-apply on DOM ready (handles any SSR mismatch) */
  document.addEventListener('DOMContentLoaded', function () {
    applyTheme(document.documentElement.getAttribute('data-bs-theme') || systemPreference());
  });
})();


/* ── 2. INLINE NOTICE API ────────────────────────────────── */
/**
 * window.skNotice(type, message, [targetId], [autoDismissMs])
 *
 * Renders a notice inside `targetId` (default: 'sheetNotice' or 'pageNotice').
 * type: 'success' | 'error' | 'warning' | 'info'
 * autoDismissMs: 0 = stays until user closes; default 3500 for success/info.
 */
(function () {
  'use strict';

  /* Icon map for each notice type */
  var ICONS = {
    success: 'fa-circle-check',
    error:   'fa-circle-exclamation',
    warning: 'fa-triangle-exclamation',
    info:    'fa-circle-info'
  };

  /* Safely escape arbitrary text for rendering inside HTML */
  function escapeHtml(str) {
    return String(str == null ? '' : str)
      .replace(/&/g,  '&amp;')
      .replace(/</g,  '&lt;')
      .replace(/>/g,  '&gt;')
      .replace(/"/g,  '&quot;')
      .replace(/'/g,  '&#039;');
  }

  /**
   * Shows a notice in `targetId`. If the element is not found, falls
   * back to the Swal-lite console log (development only).
   */
  window.skNotice = function (type, message, targetId, autoDismissMs) {
    /* Resolve container: prefer caller-supplied id, then active sheet notice area,
       then page-level notice area */
    var container = null;
    if (targetId) {
      container = document.getElementById(targetId);
    }
    if (!container) {
      container = document.getElementById('sheetNotice');
      if (container && !container.offsetParent) {
        // sheet notice area is not visible — use page notice
        container = document.getElementById('pageNotice');
      }
    }
    if (!container) {
      /* Final fallback — use the open sheet notice area */
      container = document.querySelector('.sheet-overlay.open ~ .bottom-sheet #sheetNotice')
               || document.getElementById('pageNotice');
    }
    if (!container) { return; }

    var iconClass = ICONS[type] || ICONS.info;

    var notice = document.createElement('div');
    notice.className = 'sk-notice sk-notice-' + escapeHtml(type) + ' fade-in';
    notice.innerHTML =
      '<i class="fas ' + iconClass + '"></i>' +
      '<span>' + escapeHtml(message) + '</span>' +
      '<button class="sk-notice-close" aria-label="বন্ধ করুন">' +
        '<i class="fas fa-times"></i>' +
      '</button>';

    /* Dismiss button */
    notice.querySelector('.sk-notice-close').addEventListener('click', function () {
      _dismiss(notice);
    });

    /* Prepend so newest notice is on top */
    container.insertBefore(notice, container.firstChild);

    /* Auto-dismiss for success and info (configurable, default 3500ms) */
    var delay = (typeof autoDismissMs === 'number')
      ? autoDismissMs
      : (type === 'success' || type === 'info') ? 3500 : 0;

    if (delay > 0) {
      setTimeout(function () { _dismiss(notice); }, delay);
    }
  };

  function _dismiss(el) {
    if (!el || !el.parentNode) return;
    el.style.transition = 'opacity .25s ease, transform .25s ease';
    el.style.opacity    = '0';
    el.style.transform  = 'translateY(-6px)';
    setTimeout(function () {
      if (el.parentNode) el.parentNode.removeChild(el);
    }, 260);
  }

  /** Clear all notices in a container */
  window.skClearNotices = function (targetId) {
    var container = targetId ? document.getElementById(targetId) : null;
    if (!container) container = document.getElementById('sheetNotice');
    if (!container) return;
    Array.from(container.querySelectorAll('.sk-notice')).forEach(function (el) {
      _dismiss(el);
    });
  };

})();


/* ── 3. SAFE DOM HELPERS ─────────────────────────────────── */
/**
 * window.htmlEscape(str)
 * Safe HTML escaping for building HTML strings in JavaScript.
 * Use when you must build HTML via template literals and need to
 * embed untrusted text content.
 */
window.htmlEscape = (function () {
  'use strict';
  return function htmlEscape(str) {
    return String(str == null ? '' : str)
      .replace(/&/g,  '&amp;')
      .replace(/</g,  '&lt;')
      .replace(/>/g,  '&gt;')
      .replace(/"/g,  '&quot;')
      .replace(/'/g,  '&#039;');
  };
})();

/**
 * window.fmtBdt(value)
 * Format a number as BDT currency string: ৳1,234.56
 */
window.fmtBdt = function (value) {
  return '৳' + parseFloat(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2 });
};

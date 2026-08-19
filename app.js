/* =====================================================================
 *  Sada Kalo Fashion — Shared Frontend Utilities (Vanilla JS)
 *  No external dependencies. All helpers are defensive (null-safe).
 * ===================================================================== */
(function () {
  'use strict';

  /* ---- Dark Mode ---------------------------------------------------- */
  /* FOUC prevention is handled by an inline <script> in each page <head>.
     This IIFE handles the toggle button and icon sync on page load.     */
  var root = document.documentElement;

  function applyTheme(t) {
    if (t === 'dark') { root.classList.add('dark'); }
    else              { root.classList.remove('dark'); }
    document.querySelectorAll('[data-theme-icon]').forEach(function (el) {
      el.textContent = (t === 'dark') ? '☀️' : '🌙';
    });
    try { localStorage.setItem('skTheme', t); } catch (e) { /* private mode */ }
  }

  window.toggleTheme = function () {
    applyTheme(root.classList.contains('dark') ? 'light' : 'dark');
  };

  /* Sync icon immediately (class already applied by inline head script) */
  (function () {
    var saved = 'light';
    try { saved = localStorage.getItem('skTheme') || 'light'; } catch (e) {}
    /* Ensure class matches saved preference (redundant but safe) */
    applyTheme(saved);
  })();

  /* ---- Toast Notifications ----------------------------------------- */
  window.toast = function (msg, type) {
    type = type || 'info';
    var colors = {
      ok:   'background:#059669',
      err:  'background:#dc2626',
      warn: 'background:#d97706',
      info: 'background:#1e293b',
    };
    var style = colors[type] || colors.info;

    var box = document.getElementById('skToastBox');
    if (!box) {
      box = document.createElement('div');
      box.id = 'skToastBox';
      box.style.cssText = 'position:fixed;left:50%;transform:translateX(-50%);z-index:9999;display:flex;flex-direction:column;align-items:center;gap:8px;';
      box.style.bottom = 'calc(env(safe-area-inset-bottom, 0px) + 16px)';
      document.body.appendChild(box);
    }

    var t = document.createElement('div');
    t.style.cssText = 'color:#fff;font-size:13px;font-weight:700;padding:10px 18px;border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.3);max-width:88vw;text-align:center;transition:all .28s ease;opacity:0;transform:translateY(10px);white-space:pre-line;' + style;
    t.textContent = msg;
    box.appendChild(t);

    requestAnimationFrame(function () {
      t.style.opacity = '1';
      t.style.transform = 'translateY(0)';
    });

    setTimeout(function () {
      t.style.opacity = '0';
      t.style.transform = 'translateY(10px)';
      setTimeout(function () { if (t.parentNode) t.remove(); }, 300);
    }, 2800);
  };

  /* ---- Debounced Search -------------------------------------------- */
  window.makeSearch = function (inputId, cardSelector, counterId, totalLabel) {
    var input = document.getElementById(inputId);
    if (!input) return;
    var timer = null;

    input.addEventListener('input', function () {
      var q = (input.value || '').toLowerCase().trim();
      clearTimeout(timer);
      timer = setTimeout(function () {
        var visible = 0;
        document.querySelectorAll(cardSelector).forEach(function (c) {
          var match = !q || (c.getAttribute('data-search') || '').indexOf(q) !== -1;
          c.style.display = match ? '' : 'none';
          if (match) visible++;
        });
        var counter = counterId && document.getElementById(counterId);
        if (counter) counter.textContent = q ? (visible + ' result(s)') : totalLabel;
      }, 100);
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === '/' && document.activeElement !== input) {
        e.preventDefault(); input.focus();
      }
      if (e.key === 'Escape') {
        input.value = ''; input.dispatchEvent(new Event('input')); input.blur();
      }
    });
  };

  /* ---- Camera Helpers ---------------------------------------------- */

  /** Safely stop all tracks in a MediaStream. */
  window.skStopStream = function (stream) {
    if (!stream) return;
    try { stream.getTracks().forEach(function (t) { t.stop(); }); } catch (e) {}
  };

  /** Open camera and bind to a <video> element.
   *  Returns the MediaStream on success, null on failure. */
  window.skOpenCamera = async function (videoEl, facing) {
    if (!videoEl) return null;

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      window.toast('Camera not available. HTTPS is required.', 'err');
      return null;
    }

    /* Stop any existing stream on this element */
    if (videoEl.srcObject) {
      window.skStopStream(videoEl.srcObject);
      videoEl.srcObject = null;
    }

    var stream = null;
    var constraints = [
      { video: { facingMode: facing || 'environment', width: { ideal: 1280 }, height: { ideal: 720 } } },
      { video: { facingMode: { ideal: facing || 'environment' } } },
      { video: true },
    ];

    for (var i = 0; i < constraints.length && !stream; i++) {
      try { stream = await navigator.mediaDevices.getUserMedia(constraints[i]); } catch (e) {
        if (i === constraints.length - 1) {
          var msg = e.name === 'NotAllowedError'  ? 'Camera permission denied. Please allow camera access in browser settings.'
                  : e.name === 'NotFoundError'    ? 'No camera device found.'
                  : e.name === 'NotReadableError' ? 'Camera is in use by another app.'
                  : 'Camera error: ' + (e.message || e.name);
          window.toast(msg, 'err');
          return null;
        }
      }
    }

    videoEl.srcObject = stream;
    try {
      await new Promise(function (resolve) { videoEl.onloadedmetadata = resolve; });
      await videoEl.play();
    } catch (e) { /* play() may throw on some browsers */ }

    return stream;
  };

  /** Capture current video frame → resized base64 JPEG. */
  window.skSnap = function (videoEl, canvasEl, maxW) {
    if (!videoEl || !canvasEl) return '';
    maxW = maxW || 1000;
    var w = videoEl.videoWidth  || 640;
    var h = videoEl.videoHeight || 480;
    var sf = Math.min(maxW / w, 1);
    canvasEl.width  = Math.max(1, Math.round(w * sf));
    canvasEl.height = Math.max(1, Math.round(h * sf));
    var ctx = canvasEl.getContext('2d');
    ctx.drawImage(videoEl, 0, 0, canvasEl.width, canvasEl.height);
    return canvasEl.toDataURL('image/jpeg', 0.82);
  };

  /** Load a File object → resized base64 JPEG, then call cb(dataUrl). */
  window.skLoadFile = function (file, canvasEl, maxW, cb) {
    if (!file || !canvasEl || typeof cb !== 'function') return;
    maxW = maxW || 1000;
    var reader = new FileReader();
    reader.onload = function (ev) {
      var img = new Image();
      img.onload = function () {
        var sf = Math.min(maxW / img.width, 1);
        canvasEl.width  = Math.max(1, Math.round(img.width  * sf));
        canvasEl.height = Math.max(1, Math.round(img.height * sf));
        canvasEl.getContext('2d').drawImage(img, 0, 0, canvasEl.width, canvasEl.height);
        cb(canvasEl.toDataURL('image/jpeg', 0.82));
      };
      img.onerror = function () { window.toast('Image failed to load.', 'err'); };
      img.src = ev.target.result;
    };
    reader.onerror = function () { window.toast('File read error.', 'err'); };
    reader.readAsDataURL(file);
  };

  /* Stop active camera stream when page becomes hidden (tab switch) */
  document.addEventListener('visibilitychange', function () {
    if (document.hidden && window.__skActiveStream) {
      window.skStopStream(window.__skActiveStream);
      window.__skActiveStream = null;
    }
  });

})();

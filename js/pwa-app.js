/* =====================================================================
 *  সাদা কালো ফ্যাশন — PWA Controller (pwa-app.js) — সম্পূর্ণ সংস্করণ
 *  ---------------------------------------------------------------------
 *  দায়িত্ব:
 *    1) Service Worker রেজিস্টার করা
 *    2) ব্রাউজারে এলে "ইনস্টল করুন" পপ-আপ দেখানো
 *    3) Online/Offline সংযোগ টোস্ট দেখানো
 * ===================================================================== */
(function () {
  'use strict';

  /* ---------------- কনফিগারেশন ---------------- */
  const SERVICE_WORKER_PATH = '/sw.js';
  const LOGO_PATH    = '/assets/icon/android/launchericon-192x192.logo.png';
  const POPUP_TITLE  = 'আমাদের অ্যাপটি ডাউনলোড করুন';
  const POPUP_BRAND  = 'সাদাকালো ফ্যাশন';
  const POPUP_DESC   = 'সহজেই ব্যবহার করতে আমাদের অ্যাপটি ইনস্টল করুন';
  const DISMISS_KEY  = 'skpwa_install_dismissed_at';
  const DISMISS_DAYS = 3;       // "পরে" চাপলে ৩ দিন আর দেখাবে না
  const SHOW_DELAY   = 200;    // ২ সেকেন্ড (২০০০ মিলি-সেকেন্ড)
  const TOAST_DELAY  = 400;    // ৪ সেকেন্ড (৪০০০ মিলি-সেকেন্ড)

  let deferredInstallPrompt = null;
  let toastTimer = null;
  /* =================================================================
   *  Service Worker রেজিস্ট্রেশন
   * ================================================================= */
  function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) return;

    window.addEventListener('load', function () {
      navigator.serviceWorker.register(SERVICE_WORKER_PATH)
        .then(function (reg) {
          // নতুন worker পাওয়া গেলে সাথে সাথে অ্যাক্টিভেট করি
          reg.addEventListener('updatefound', function () {
            var newWorker = reg.installing;
            newWorker.addEventListener('statechange', function () {
              if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                newWorker.postMessage({ type: 'SKIP_WAITING' });
              }
            });
          });
        })
        .catch(function (err) { console.warn('[PWA] SW রেজিস্ট্রেশন ব্যর্থ:', err); });
    });
  }

  /* =================================================================
   *  সহায়ক ফাংশন
   * ================================================================= */
  function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches ||
           window.navigator.standalone === true;
  }
  function isIos() {
    return /iphone|ipad|ipod/i.test(window.navigator.userAgent) && !window.MSStream;
  }
  function wasRecentlyDismissed() {
    try {
      const ts = parseInt(localStorage.getItem(DISMISS_KEY) || '', 10);
      if (!ts) return false;
      // ১ দিন = ৮৬৪০০০০০ মিলিসেকেন্ড
      return ((Date.now() - ts) / (86400000)) < DISMISS_DAYS;
    } catch (e) { return false; }
  }
  function rememberDismiss() {
    try { localStorage.setItem(DISMISS_KEY, String(Date.now())); } catch (e) {}
  }

  /* =================================================================
   *  Online/Offline টোস্ট
   * ================================================================= */
  function buildToast() {
    if (document.getElementById('skpwaToast')) {
      return document.getElementById('skpwaToast');
    }
    var toast = document.createElement('div');
    toast.className = 'skpwa-toast';
    toast.id = 'skpwaToast';
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'polite');
    toast.innerHTML =
      '<span class="skpwa-dot"></span>' +
      '<span class="skpwa-toast-text" id="skpwaToastText">সংযোগ ফিরে এসেছে</span>';
    document.body.appendChild(toast);
    return toast;
  }

  function showToast(online) {
    var toast = buildToast();
    var textEl = document.getElementById('skpwaToastText');
    var dot = toast.querySelector('.skpwa-dot');

    // আগের টাইমার ক্লিয়ার করি
    if (toastTimer) {
      clearTimeout(toastTimer);
      toastTimer = null;
    }

    // আগের স্টেট ক্লাস সরাই
    toast.classList.remove('skpwa-toast--online', 'skpwa-toast--offline');

    if (online) {
      toast.classList.add('skpwa-toast--online');
      if (textEl) textEl.textContent = '🟢 সংযোগ ফিরে এসেছে';
    } else {
      toast.classList.add('skpwa-toast--offline');
      if (textEl) textEl.textContent = '🔴 সংযোগ বিচ্ছিন্ন — আপনি অফলাইনে আছেন';
    }

    // দেখাও
    toast.classList.add('skpwa-show');

    // নির্দিষ্ট সময় পর আড়াল করি
    toastTimer = setTimeout(function () {
      toast.classList.remove('skpwa-show');
      toastTimer = null;
    }, TOAST_DELAY);
  }

  function initConnectivityListeners() {
    window.addEventListener('online', function () {
      showToast(true);
    });
    window.addEventListener('offline', function () {
      showToast(false);
    });
  }

  /* =================================================================
   *  ইনস্টল পপ-আপ তৈরি
   * ================================================================= */
  function buildPopup() {
    if (document.getElementById('skpwaModal')) {
      return document.getElementById('skpwaModal');
    }

    var overlay = document.createElement('div');
    overlay.className = 'skpwa-modal';
    overlay.id = 'skpwaModal';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-label', POPUP_TITLE);

    overlay.innerHTML =
      '<div class="skpwa-modal__card">' +
        '<img class="skpwa-modal__logo" src="' + LOGO_PATH + '" alt="' + POPUP_BRAND + '" loading="lazy">' +
        '<div id="skpwaBody">' +
          '<h2 class="skpwa-modal__title">' + POPUP_TITLE + '</h2>' +
          '<p class="skpwa-modal__brand">' + POPUP_BRAND + '</p>' +
          '<p class="skpwa-modal__desc">' + POPUP_DESC + '</p>' +
          '<div class="skpwa-modal__actions">' +
            '<button type="button" class="skpwa-btn skpwa-btn--primary" id="skpwaInstallYes">ইনস্টল করুন</button>' +
            '<button type="button" class="skpwa-btn skpwa-btn--ghost" id="skpwaInstallNo">পরে</button>' +
          '</div>' +
        '</div>' +
      '</div>';

    document.body.appendChild(overlay);

    document.getElementById('skpwaInstallYes').addEventListener('click', onInstallClick);
    document.getElementById('skpwaInstallNo').addEventListener('click', function () {
      closePopup();
      rememberDismiss();
    });
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) { closePopup(); rememberDismiss(); }
    });
    return overlay;
  }

  function openPopup() {
    var overlay = buildPopup();
    requestAnimationFrame(function () { overlay.classList.add('skpwa-show'); });
  }
  function closePopup() {
    var overlay = document.getElementById('skpwaModal');
    if (overlay) overlay.classList.remove('skpwa-show');
  }

  /* ইনস্টল বোতাম: নেটিভ সম্ভব হলে নেটিভ, নাহলে নির্দেশনা */
  function onInstallClick() {
    if (deferredInstallPrompt) {
      deferredInstallPrompt.prompt();
      deferredInstallPrompt.userChoice.then(function (choice) {
        if (!choice || choice.outcome !== 'accepted') {
          rememberDismiss();
        }
        closePopup();
        deferredInstallPrompt = null;
      });
    } else {
      showInstructions();   // iOS / যে ব্রাউজারে নেটিভ প্রম্পট নেই
    }
  }

  /* নেটিভ প্রম্পট না থাকলে ধাপে ধাপে নির্দেশনা */
  function showInstructions() {
    var body = document.getElementById('skpwaBody');
    if (!body) return;

    var steps;
    if (isIos()) {
      steps =
        '<div class="skpwa-step">' +
          '<span class="skpwa-step__num">১</span>' +
          '<span class="skpwa-step__txt">নিচের <strong>শেয়ার</strong> বোতামে চাপ দিন</span>' +
        '</div>' +
        '<div class="skpwa-step">' +
          '<span class="skpwa-step__num">২</span>' +
          '<span class="skpwa-step__txt"><strong>"Add to Home Screen"</strong> নির্বাচন করুন</span>' +
        '</div>';
    } else {
      steps =
        '<div class="skpwa-step">' +
          '<span class="skpwa-step__num">১</span>' +
          '<span class="skpwa-step__txt">ব্রাউজারের <strong>মেনু (⋮)</strong> খুলুন</span>' +
        '</div>' +
        '<div class="skpwa-step">' +
          '<span class="skpwa-step__num">২</span>' +
          '<span class="skpwa-step__txt"><strong>"অ্যাপ ইনস্টল করুন"</strong> / "Install app" চাপুন</span>' +
        '</div>';
    }

    body.innerHTML =
      '<h2 class="skpwa-modal__title">ইনস্টল করার নিয়ম</h2>' +
      '<p class="skpwa-modal__brand">' + POPUP_BRAND + '</p>' +
      '<div class="skpwa-steps">' + steps + '</div>' +
      '<div class="skpwa-modal__actions">' +
        '<button type="button" class="skpwa-btn skpwa-btn--primary" id="skpwaDone">বুঝেছি</button>' +
      '</div>';

    document.getElementById('skpwaDone').addEventListener('click', function () {
      closePopup();
      rememberDismiss();
    });
  }

  /* beforeinstallprompt ইভেন্ট ধরে রাখি (থাকলে নেটিভ ইনস্টল হবে) */
  function listenForInstallPrompt() {
    window.addEventListener('beforeinstallprompt', function (event) {
      event.preventDefault();
      deferredInstallPrompt = event;
    });
    window.addEventListener('appinstalled', function () {
      closePopup();
      deferredInstallPrompt = null;
      rememberDismiss();
    });
  }

  /* পপ-আপ দেখানোর সিদ্ধান্ত */
  function maybeShowPopup() {
    if (isStandalone() || wasRecentlyDismissed()) return;
    setTimeout(openPopup, SHOW_DELAY);
  }

  /* =================================================================
   *  ইনিশিয়ালাইজেশন
   * ================================================================= */
  function init() {
    registerServiceWorker();
    listenForInstallPrompt();
    initConnectivityListeners();
    maybeShowPopup();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

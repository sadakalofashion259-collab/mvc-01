'use strict';

/* =====================================================================
 *  সাদা কালো ফ্যাশন — Service Worker (CSS + logo/banner ক্যাশ)
 *  ---------------------------------------------------------------------
 *  আচরণ:
 *   • যেকোনো ফোল্ডারের .css ফাইল → Cache-first (ভার্সন বদল পর্যন্ত)
 *   • যেকোনো ফোল্ডারের logo/banner নামের ছবি → Cache-first (ভার্সন বদল পর্যন্ত)
 *   • EXACT_NAMES তালিকার নামও ক্যাশ হবে
 *   • CACHE_VERSION না বদলানো পর্যন্ত এইগুলোই সব পেজে দেখাবে
 *   • আর কোনো js/পেজ/ডেটা কিচ্ছু ক্যাশ হবে না — সব সরাসরি সার্ভার থেকে
 *   • ইন্টারনেট না থাকলে কোনো পেজ খুললে offline.html দেখাবে
 *   • কোনো Push / Notification কোড নেই
 *
 *  ★ নতুন logo/banner/CSS দিলে নিচের CACHE_VERSION একধাপ বাড়াও ('v22' → 'v23')।
 * ===================================================================== */

const CACHE_VERSION = 'v45';
const ASSET_CACHE   = `sadakalo-assets-${CACHE_VERSION}`;
const OFFLINE_URL   = '/offline.html';

/* ইনস্টলের সময় যা নিশ্চিতভাবে জমা থাকবে (অফলাইন শেল + মূল আইকন) */
const PRECACHE_ASSETS = [
  OFFLINE_URL,
  '/assets/icon/android/launchericon-192x192.logo.png',
  '/assets/icon/android/launchericon-512x512.logo.png'
];

/* ছবির এক্সটেনশন */
const IMAGE_EXT = /\.(png|jpe?g|webp|svg|gif|ico)$/i;

/* CSS ফাইল (যেকোনো ফোল্ডার) */
const CSS_EXT = /\.css$/i;

/* নামে এই শব্দগুলো থাকলেই logo/banner ধরা হবে (ছোট/বড় হাত উপেক্ষা করে) */
const NAME_KEYWORDS = ['logo', 'banner'];

/* এই নির্দিষ্ট নামগুলোও ধরা হবে (logo/banner শব্দ না থাকলেও) */
const EXACT_NAMES = [
  'sada_kalo_fashion.png',
  'sada_kalo_fashion_banner.jpg'
  // চাইলে আরও নির্দিষ্ট নাম এখানে যোগ করো
];

/* কোনো URL CSS কিনা — শুধু ফাইলের এক্সটেনশন দেখে */
function isCss(pathname) {
  return CSS_EXT.test(pathname);
}

/* কোনো URL logo/banner কিনা — শুধু ফাইলের নাম দেখে সিদ্ধান্ত */
function isLogoOrBanner(pathname) {
  const fileName = pathname.split('/').pop().toLowerCase();
  if (!IMAGE_EXT.test(fileName)) return false;
  if (EXACT_NAMES.map((n) => n.toLowerCase()).includes(fileName)) return true;
  return NAME_KEYWORDS.some((keyword) => fileName.includes(keyword));
}

/* ---------------------------------------------------------------------
 *  INSTALL — অফলাইন শেল + মূল আইকন প্রি-ক্যাশ
 * ------------------------------------------------------------------- */
self.addEventListener('install',(event) => {
  event.waitUntil((async () => {
    const cache = await caches.open(ASSET_CACHE);
    for (const url of PRECACHE_ASSETS) {
      try {
        const response = await fetch(url, { cache: 'no-store' });
        if (response && response.ok) {
          await cache.put(url, response.clone());
        } else {
          console.warn('[SW] precache বাদ (status ' + (response && response.status) + '):', url);
        }
      } catch (err) {
        console.warn('[SW] precache ব্যর্থ:', url, err);
      }
    }
    await self.skipWaiting();
  })());
});

/* ---------------------------------------------------------------------
 *  ACTIVATE — পুরনো version-এর সব ক্যাশ মুছে ফেলা
 * ------------------------------------------------------------------- */
self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(
      keys.filter((key) => key !== ASSET_CACHE).map((key) => caches.delete(key))
    );
    await self.clients.claim();
  })());
});

/* ---------------------------------------------------------------------
 *  FETCH — মূল রাউটিং
 * ------------------------------------------------------------------- */
self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (!url.protocol.startsWith('http')) return;

  // ১) CSS ফাইল (যেকোনো ফোল্ডার) → Cache-first, মেয়াদ নেই
  if (url.origin === self.location.origin && isCss(url.pathname)) {
    event.respondWith(serveAsset(request));
    return;
  }

  // ২) নামে logo/banner থাকলে (যেকোনো ফোল্ডার) → Cache-first, মেয়াদ নেই
  if (url.origin === self.location.origin && isLogoOrBanner(url.pathname)) {
    event.respondWith(serveAsset(request));
    return;
  }

  // ৩) পেজ খোলা → নেটওয়ার্ক; অফলাইন হলে offline.html
  if (request.mode === 'navigate' || request.destination === 'document') {
    event.respondWith(servePage(request));
    return;
  }

  // ৪) বাকি সব (js, ছবি, ডেটা) → কিচ্ছু ক্যাশ নয়, ব্রাউজার সরাসরি নেটওয়ার্ক থেকে আনবে
});

/* CSS/logo/banner পরিবেশন: ক্যাশে থাকলে ক্যাশ, নাহলে এনে ক্যাশে রাখি */
async function serveAsset(request) {
  const cached = await caches.match(request);
  if (cached) return cached;
  try {
    const response = await fetch(request);
    if (response && response.ok) {
      const cache = await caches.open(ASSET_CACHE);
      cache.put(request, response.clone());
    }
    return response;
  } catch (err) {
    return cached || Response.error();
  }
}

/* পেজ পরিবেশন: নেটওয়ার্ক; ব্যর্থ হলে offline.html */
async function servePage(request) {
  try {
    return await fetch(request);
  } catch (err) {
    const offline = await caches.match(OFFLINE_URL, { ignoreSearch: true });
    if (offline) return offline;
    return new Response(
      '<h1>═════ সাদা-কালো ফ্যাশন ═════
      আপনি অফলাইনে আছেন</h1>',
      { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
    );
  }
}

/* নতুন version তৎক্ষণাৎ সক্রিয় করতে */
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

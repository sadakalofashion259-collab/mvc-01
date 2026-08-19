<?php
// 🤖 দৈনিক দোকান হিসাব খাতা
// Lottie Animation — localStorage cache system
// Once downloaded → stored in localStorage → no re-download needed
?>

<style>
  .robot-lottie-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 200px;
    height: 200px;
    position: relative;
  }

  /* Skeleton loader while animation loads */
  .robot-lottie-skeleton {
    width: 200px;
    height: 200px;
    border-radius: 50%;
    background: linear-gradient(90deg, #e0e0e0 25%, #f5f5f5 50%, #e0e0e0 75%);
    background-size: 200% 100%;
    animation: shimmer 1.4s infinite;
    position: absolute;
    top: 0;
    left: 0;
  }

  @keyframes shimmer {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
  }

  .robot-lottie-skeleton.hidden {
    display: none;
  }
</style>

<div class="robot-lottie-wrapper" id="robotLottieContainer">
  <div class="robot-lottie-skeleton" id="robotSkeleton"></div>
  <!-- dotlottie-wc player এখানে JS দিয়ে inject হবে -->
</div>

<!-- dotlottie-wc লাইব্রেরি — শুধু একবার লোড হয় (module script) -->
<script type="module">
  (async () => {
    // ─────────────────────────────────────────────
    // CONFIG
    // ─────────────────────────────────────────────
    const LOTTIE_URL     = 'https://lottie.host/1b30fa08-92da-4e38-81b5-e6236b92f63a/cGNSbBLSFu.lottie';
    const STORAGE_KEY    = 'lottie_cache__robot_hisab';   // localStorage key
    const STORAGE_VER    = 'v1';                           // version বদলালে re-download হবে
    const STORAGE_VER_KEY = 'lottie_cache__robot_hisab__ver';

    const container  = document.getElementById('robotLottieContainer');
    const skeleton   = document.getElementById('robotSkeleton');

    // ─────────────────────────────────────────────
    // HELPER: localStorage-এ cache আছে কিনা দেখো
    // ─────────────────────────────────────────────
    function getCached() {
      try {
        const savedVer  = localStorage.getItem(STORAGE_VER_KEY);
        const savedData = localStorage.getItem(STORAGE_KEY);
        if (savedVer === STORAGE_VER && savedData) {
          return savedData; // Base64 Data URL
        }
      } catch (e) {
        // localStorage blocked (private mode) — gracefully ignore
      }
      return null;
    }

    // ─────────────────────────────────────────────
    // HELPER: localStorage-এ সেভ করো
    // ─────────────────────────────────────────────
    function saveCache(dataUrl) {
      try {
        localStorage.setItem(STORAGE_KEY, dataUrl);
        localStorage.setItem(STORAGE_VER_KEY, STORAGE_VER);
      } catch (e) {
        // QuotaExceededError — নীরবে ignore করো
      }
    }

    // ─────────────────────────────────────────────
    // HELPER: Blob → Base64 Data URL
    // ─────────────────────────────────────────────
    function blobToDataUrl(blob) {
      return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload  = () => resolve(reader.result);
        reader.onerror = () => reject(reader.error);
        reader.readAsDataURL(blob);
      });
    }

    // ─────────────────────────────────────────────
    // MAIN: dotlottie-wc player তৈরি করো
    // ─────────────────────────────────────────────
    async function mountPlayer(src) {
      // dotlottie-wc custom element define না হলে অপেক্ষা করো
      if (!customElements.get('dotlottie-wc')) {
        await customElements.whenDefined('dotlottie-wc');
      }

      const player = document.createElement('dotlottie-wc');
      player.setAttribute('src', src);
      player.setAttribute('autoplay', '');
      player.setAttribute('loop', '');
      player.style.cssText = 'width:200px;height:200px;';

      // player ready হলে skeleton সরাও
      player.addEventListener('ready', () => {
        skeleton.classList.add('hidden');
      });

      // fallback: 3s পরেও skeleton সরিয়ে দাও
      setTimeout(() => skeleton.classList.add('hidden'), 3000);

      container.appendChild(player);
    }

    // ─────────────────────────────────────────────
    // dotlottie-wc script inject (একবারই করে)
    // ─────────────────────────────────────────────
    async function loadDotLottieScript() {
      if (customElements.get('dotlottie-wc')) return; // already loaded

      await new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.type    = 'module';
        s.src     = 'https://unpkg.com/@lottiefiles/dotlottie-wc@0.9.10/dist/dotlottie-wc.js';
        s.onload  = resolve;
        s.onerror = reject;
        document.head.appendChild(s);
      });
    }

    // ─────────────────────────────────────────────
    // FLOW START
    // ─────────────────────────────────────────────
    try {
      // Step 1: dotlottie-wc লাইব্রেরি লোড করো
      await loadDotLottieScript();

      // Step 2: cache চেক করো
      const cached = getCached();

      if (cached) {
        // ✅ Cache hit — ইন্টারনেট লাগবে না
        await mountPlayer(cached);

      } else {
        // ⬇️ Cache miss — একবার ডাউনলোড করো
        const response = await fetch(LOTTIE_URL);
        if (!response.ok) throw new Error(`Fetch failed: ${response.status}`);

        const blob    = await response.blob();
        const dataUrl = await blobToDataUrl(blob);

        // localStorage-এ সেভ করো
        saveCache(dataUrl);

        // Player mount করো
        await mountPlayer(dataUrl);
      }

    } catch (err) {
      // Error হলে skeleton সরাও, সরাসরি URL দিয়ে fallback চেষ্টা করো
      skeleton.classList.add('hidden');

      try {
        await mountPlayer(LOTTIE_URL);
      } catch (fallbackErr) {
        // সব ব্যর্থ — container খালি রাখো (ইউজারকে error দেখাবে না)
        container.innerHTML = '';
      }
    }

  })();
</script>

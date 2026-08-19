/**
 * button-system.js
 * ─────────────────────────────────────────────────────────────
 * Unified tactile-feedback engine for SADA KALO FASHION UI.
 *
 * What it does, for every element matching SELECTOR:
 *   1. Plays a short synthesized "click" tone via the native
 *      Web Audio API — no external audio file, no third-party
 *      library, nothing to download.
 *   2. Spawns a short-lived ripple element at the pointer's
 *      position (visual only, auto-removed after the animation).
 *   3. Adds/removes an `.is-pressed` class as a safety net for
 *      devices/browsers where :active does not fire reliably
 *      (e.g. some Android WebViews).
 *
 * No dependencies. Include with:
 *   <script src="assets/js/button-system.js" defer></script>
 * ─────────────────────────────────────────────────────────────
 */
(function () {
    'use strict';

    var INTERACTIVE_SELECTOR = [
        '.nav-menu-btn',
        '.nav-icon-btn',
        '.nav-profile-btn',
        '.nav-theme-btn',
        '.round-3d-btn',
        '.sb-close-btn',
        '.sb-folder-btn',
        '.sb-theme-toggle',
        '.bn-item',
        '.bn-center',
        '.btn-prem',
        '.sk-tab-btn',
        '.sk-pass-toggle',
        '.sk-modal-close',
        '.alert-close',
        '.coll-call-btn',
        '.section-theme-pill'
    ].join(', ');

    var sharedAudioContext = null;
    var audioUnlocked = false;

    function getAudioContext() {
        var AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass) {
            return null;
        }
        if (!sharedAudioContext) {
            sharedAudioContext = new AudioContextClass();
        }
        if (sharedAudioContext.state === 'suspended') {
            sharedAudioContext.resume().catch(function () {
                /* Autoplay policy — will retry on next user gesture. */
            });
        }
        return sharedAudioContext;
    }

    /**
     * Synthesizes a short, soft "click" blip:
     * a quick downward pitch sweep with a fast attack/decay envelope,
     * so it reads as a crisp tactile click rather than a beep.
     */
    function playClickTone() {
        var context = getAudioContext();
        if (!context) {
            return;
        }
        audioUnlocked = true;

        var startTime = context.currentTime;
        var oscillator = context.createOscillator();
        var gainNode = context.createGain();

        oscillator.type = 'sine';
        oscillator.frequency.setValueAtTime(680, startTime);
        oscillator.frequency.exponentialRampToValueAtTime(300, startTime + 0.05);

        gainNode.gain.setValueAtTime(0.0001, startTime);
        gainNode.gain.exponentialRampToValueAtTime(0.15, startTime + 0.008);
        gainNode.gain.exponentialRampToValueAtTime(0.0001, startTime + 0.09);

        oscillator.connect(gainNode);
        gainNode.connect(context.destination);

        oscillator.start(startTime);
        oscillator.stop(startTime + 0.1);
    }

    function spawnRipple(targetElement, pointerEvent) {
        var rect = targetElement.getBoundingClientRect();
        var rippleSize = Math.max(rect.width, rect.height) * 1.4;

        var pointerX = (pointerEvent && typeof pointerEvent.clientX === 'number')
            ? pointerEvent.clientX
            : rect.left + rect.width / 2;
        var pointerY = (pointerEvent && typeof pointerEvent.clientY === 'number')
            ? pointerEvent.clientY
            : rect.top + rect.height / 2;

        var rippleElement = document.createElement('span');
        rippleElement.className = 'sk-ripple';
        rippleElement.style.width = rippleSize + 'px';
        rippleElement.style.height = rippleSize + 'px';
        rippleElement.style.left = (pointerX - rect.left - rippleSize / 2) + 'px';
        rippleElement.style.top = (pointerY - rect.top - rippleSize / 2) + 'px';

        var computedPosition = window.getComputedStyle(targetElement).position;
        if (computedPosition === 'static') {
            targetElement.style.position = 'relative';
        }
        targetElement.style.overflow = targetElement.style.overflow || 'hidden';

        targetElement.appendChild(rippleElement);
        rippleElement.addEventListener('animationend', function () {
            rippleElement.remove();
        });
    }

    function handlePointerDown(event) {
        var targetButton = event.target.closest(INTERACTIVE_SELECTOR);
        if (!targetButton || targetButton.hasAttribute('disabled')) {
            return;
        }
        targetButton.classList.add('is-pressed');
        playClickTone();
        spawnRipple(targetButton, event);
    }

    function handlePointerRelease(event) {
        var targetButton = event.target.closest(INTERACTIVE_SELECTOR);
        if (targetButton) {
            targetButton.classList.remove('is-pressed');
            return;
        }
        var pressedButtons = document.querySelectorAll('.is-pressed');
        for (var index = 0; index < pressedButtons.length; index += 1) {
            pressedButtons[index].classList.remove('is-pressed');
        }
    }

    document.addEventListener('pointerdown', handlePointerDown, { passive: true });
    document.addEventListener('pointerup', handlePointerRelease, { passive: true });
    document.addEventListener('pointercancel', handlePointerRelease, { passive: true });

    /* Some mobile browsers require an explicit first gesture to unlock
       audio output; this silently primes it on the very first touch. */
    document.addEventListener('touchstart', function primeAudioOnce() {
        if (!audioUnlocked) {
            getAudioContext();
        }
        document.removeEventListener('touchstart', primeAudioOnce);
    }, { passive: true, once: true });
})();

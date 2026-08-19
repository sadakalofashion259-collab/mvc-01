<?php
/**
 * VIEW: login/login_scripts.php
 * Covers: slider auto-rotate | Turnstile callback | password eye-toggle | PWA SW
 * ADD: skfToggleEye() — চোখ পিটপিট। অন্য কিছু পরিবর্তন হয়নি।
 */
?>
<script>
/* ── Image Slider ────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
    var slides = document.querySelectorAll('.slide');
    if (slides.length > 1) {
        var cur = 0;
        setInterval(function () {
            slides[cur].classList.remove('active');
            cur = (cur + 1) % slides.length;
            slides[cur].classList.add('active');
        }, 3000);
    } else if (slides.length === 1) {
        slides[0].classList.add('active');
    }
});

/* ── Cloudflare Turnstile Callback ───────────────────────────── */
function onTurnstileSuccess(token) {
    if (!token) return;
    var stage1 = document.getElementById('recaptcha-stage');
    var stage2 = document.getElementById('login-stage');
    if (stage1) stage1.style.display = 'none';
    if (stage2) stage2.style.display = 'flex';
    var firstInput = stage2 ? stage2.querySelector('input[type="text"]') : null;
    if (firstInput) setTimeout(function () { firstInput.focus(); }, 100);
}

/* ── Password Eye Toggle (চোখ পিটপিট) ─────────────────────────── */
function skfToggleEye(btn) {
    var wrap = btn.closest('.skf-pass-wrap');
    var inp  = wrap ? wrap.querySelector('input') : null;
    var icon = btn.querySelector('i');
    if (!inp || !icon) return;
    var willShow   = (inp.type === 'password');
    inp.type       = willShow ? 'text' : 'password';
    icon.className = willShow ? 'fas fa-eye-slash' : 'fas fa-eye';
    btn.setAttribute('aria-label', willShow ? 'পাসওয়ার্ড লুকান' : 'পাসওয়ার্ড দেখুন');
    btn.classList.remove('blink');
    void btn.offsetWidth;
    btn.classList.add('blink');
}

/* ══════════════════════════════════════════════════════════════
   বায়োমেট্রিক লগইন (WebAuthn) — passwordless
   ══════════════════════════════════════════════════════════════ */
(function () {
    var btn = document.getElementById('bioLoginBtn');
    if (!btn) return;

    /* base64url ↔ ArrayBuffer */
    function b64uToBuf(s) {
        s = s.replace(/-/g, '+').replace(/_/g, '/');
        var pad = s.length % 4; if (pad) s += '===='.slice(pad);
        var bin = atob(s), buf = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) buf[i] = bin.charCodeAt(i);
        return buf.buffer;
    }
    function bufToB64u(buf) {
        var bytes = new Uint8Array(buf), bin = '';
        for (var i = 0; i < bytes.length; i++) bin += String.fromCharCode(bytes[i]);
        return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }
    function post(params) {
        var body = new URLSearchParams(params);
        return fetch('index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (r) { return r.json(); });
    }

    /* সাপোর্ট থাকলেই বাটন দেখাও (অটো-প্রম্পট নয় — শুধু বাটনে ক্লিক করলে আসবে) */
    var wrap = document.getElementById('bioLoginWrap');
    if (window.PublicKeyCredential && wrap) {
        wrap.style.display = 'flex';
    }

    function runBioLogin(isAuto) {
        if (!window.PublicKeyCredential) {
            if (!isAuto) { alert('এই ব্রাউজার/ডিভাইসে বায়োমেট্রিক সাপোর্ট নেই।'); }
            return;
        }
        var uname = (document.querySelector('input[name="username"]') || {}).value || '';
        btn.disabled = true;
        var orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        post({ webauthn: 'auth_options', username: uname })
        .then(function (opt) {
            if (!opt.ok) throw new Error(opt.msg || 'অপশন এরর');
            var pk = {
                challenge: b64uToBuf(opt.challenge),
                rpId: opt.rpId,
                timeout: opt.timeout || 60000,
                userVerification: opt.userVerification || 'required',
                allowCredentials: (opt.allowCredentials || []).map(function (c) {
                    return { type: 'public-key', id: b64uToBuf(c.id) };
                })
            };
            return navigator.credentials.get({ publicKey: pk });
        })
        .then(function (cred) {
            var r = cred.response;
            return post({
                webauthn: 'auth_verify',
                id: cred.id,
                clientDataJSON:    bufToB64u(r.clientDataJSON),
                authenticatorData: bufToB64u(r.authenticatorData),
                signature:         bufToB64u(r.signature)
            });
        })
        .then(function (res) {
            if (res.ok) {
                btn.innerHTML = '<i class="fas fa-check"></i>';
                window.location.href = res.redirect || 'dashboard.php';
            } else {
                throw new Error(res.msg || 'যাচাই ব্যর্থ');
            }
        })
        .catch(function (err) {
            btn.disabled = false; btn.innerHTML = orig;
            // অটো-প্রম্পট বাতিল হলে নীরবে থামো (পাসওয়ার্ড দিয়ে লগইন করতে পারবে)
            if (isAuto && err && err.name === 'NotAllowedError') { return; }
            var m = (err && err.name === 'NotAllowedError')
                ? 'বাতিল করা হয়েছে বা সময় শেষ।'
                : (err && err.message ? err.message : 'বায়োমেট্রিক লগইন ব্যর্থ।');
            alert('❌ ' + m);
        });
    }

    btn.addEventListener('click', function () { runBioLogin(false); });
})();
</script>
<script src="/pwa.js"></script>

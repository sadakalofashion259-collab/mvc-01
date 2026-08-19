<?php
/**
 * VIEW: login/login_form.php
 * CSS: .login-box | .logo-wrapper | .logo | .main-title | .sub-title
 *      .msg-container | form | input | #loginBtn | .forgot-pass
 * Vars: $loginCsrfToken, $loginResultHtml
 * ADD: পাসওয়ার্ড eye-toggle (চোখ পিটপিট) — অন্য কিছু পরিবর্তন হয়নি।
 */
?>
<style>
/* ── Password Eye Toggle (চোখ পিটপিট) ───────────────────────── */
#login-stage .skf-pass-wrap { position: relative; width: 100%; margin-bottom: 8px; }
#login-stage .skf-pass-wrap > input { margin-bottom: 0; padding-right: 48px; }
#login-stage .skf-eye {
    position: absolute; top: 50%; right: 14px; transform: translateY(-50%);
    width: 30px; height: 30px; padding: 0;
    display: flex; align-items: center; justify-content: center;
    border: none; background: transparent; cursor: pointer;
    color: #64748b; font-size: 16px; -webkit-appearance: none;
}
#login-stage .skf-eye:active { color: #1e3a8a; }
#login-stage .skf-eye.blink i { animation: skfBlink .25s ease; }
@keyframes skfBlink { 0%, 100% { transform: scaleY(1); } 50% { transform: scaleY(.1); } }

/* ── পাশাপাশি লেআউট: বাঁয়ে ইনপুট, ডানে ফিঙ্গারপ্রিন্ট ──── */
#login-stage {
    flex-direction: row !important;
    align-items: center; justify-content: center;
    gap: clamp(10px, 3vw, 16px);
    width: 100%; max-width: 360px; margin: 0 auto;
}
.skf-fields-col {
    flex: 1 1 auto; min-width: 0;
    display: flex; flex-direction: column; align-items: center;
}
.skf-fields-col input { font-size: clamp(12px, 3.2vw, 14px); padding: clamp(8px,2vh,11px) 14px; }
.skf-fields-col #loginBtn { width: 100%; }

/* ── বায়োমেট্রিক লগইন — গোলাকার আইকন বাটন (ডানে) ───────── */
.bio-login-wrap {
    display: flex; flex-direction: column; align-items: center;
    gap: 6px; margin: 0; flex-shrink: 0;
}
.bio-login-btn {
    position: relative;
    width: 64px; height: 64px; padding: 0;
    display: inline-flex; align-items: center; justify-content: center;
    background: radial-gradient(circle at 35% 30%, #1e293b, #0f172a 75%);
    color: #fff; border: none; border-radius: 50%;
    cursor: pointer; -webkit-appearance: none;
    box-shadow: 0 6px 0 #0b1120, 0 10px 18px rgba(15,23,42,.35);
    transition: transform .12s, box-shadow .12s;
}
.bio-login-btn::before {
    content: ""; position: absolute; inset: -7px;
    border-radius: 50%; border: 2px solid rgba(96,165,250,.55);
    animation: bioPulse 2s ease-out infinite;
}
@keyframes bioPulse {
    0%   { transform: scale(.85); opacity: .9; }
    100% { transform: scale(1.25); opacity: 0; }
}
.bio-login-btn:active { transform: translateY(6px); box-shadow: 0 0 0 #0b1120; }
.bio-login-btn i { font-size: 28px; color: #60a5fa; position: relative; }
.bio-login-btn[disabled] { opacity: .7; cursor: progress; }
.bio-login-btn[disabled]::before { animation: none; }
.bio-login-label {
    font-size: 11.5px; font-weight: 800; color: #475569; letter-spacing: .3px;
}

/* ── নিচের লিংক সারি (সেটআপ + রিসেট) ───────────────────────── */
.login-foot-links {
    display: flex; flex-wrap: wrap; gap: 8px; justify-content: center;
    width: 100%; margin-top: 6px;
}
.login-foot-links .foot-link {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 14px; border-radius: 22px;
    font-size: clamp(11px, 3vw, 13px); font-weight: 800; text-decoration: none;
    background: #f1f5f9; color: #1e293b; border: 1px solid #e2e8f0;
    box-shadow: 0 2px 0 #e2e8f0; transition: transform .1s;
}
.login-foot-links .foot-link:active { transform: translateY(2px); box-shadow: none; }
.login-foot-links .bio-setup-link {
    background: linear-gradient(to bottom, #eff6ff, #dbeafe);
    color: #1d4ed8; border-color: #bfdbfe; box-shadow: 0 2px 0 #bfdbfe;
}
.login-foot-links .bio-setup-link i { color: #2563eb; }
</style>

<div class="login-box">

    <!-- Logo -->
    <div class="logo-wrapper">
        <img src="logo.png" class="logo" alt="Sada Kalo Fashion">
    </div>

    <!-- Brand -->
    <div class="title-group">
        <h2 class="main-title">═════ সাদা-কালো ফ্যাশন ═════</h2>
        <div class="sub-title">দৈনিক হিসাব খাতা-2026</div>
    </div>

    <!-- Flash Message -->
    <?php if (!empty($loginResultHtml)): ?>
        <div class="msg-container"><?php echo $loginResultHtml; ?></div>
    <?php endif; ?>

    <!-- Form -->
    <form method="POST" action="index.php">
        <input type="hidden" name="csrf_token"
               value="<?php echo htmlspecialchars($loginCsrfToken ?? '', ENT_QUOTES, 'UTF-8'); ?>">

        <!-- Stage 1: CAPTCHA -->
        <div id="recaptcha-stage">
            <p class="captcha-welcome-text">Welcome To My Sada Kalo Fashion</p>
            <div class="cf-turnstile"
                 data-sitekey="0x4AAAAAADfIQjRCOxiHGO2k"
                 data-callback="onTurnstileSuccess"></div>
        </div>

        <!-- Stage 2: Login fields (hidden until CAPTCHA passes) — বাঁয়ে ফর্ম, ডানে ফিঙ্গারপ্রিন্ট -->
        <div id="login-stage">
            <div class="skf-fields-col">
                <input type="text"     name="username" placeholder="═════ সাদা-কালো ফ্যাশন ═════"
                       required autocomplete="username" autocapitalize="off" autocorrect="off">

                <div class="skf-pass-wrap">
                    <input type="password" name="password" placeholder="═════ সাদা-কালো ফ্যাশন ═════"
                           required autocomplete="current-password">
                    <button type="button" class="skf-eye" onclick="skfToggleEye(this)"
                            aria-label="পাসওয়ার্ড দেখুন">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>

                <button type="submit" name="login_btn" id="loginBtn">
                    <i class="fas fa-sign-in-alt"></i> Sada Kalo
                </button>
            </div>

            <!-- বায়োমেট্রিক লগইন (WebAuthn) — গোলাকার আইকন, শুধু সাপোর্টেড ডিভাইসে -->
            <div class="bio-login-wrap" id="bioLoginWrap" style="display:none;">
                <button type="button" id="bioLoginBtn" class="bio-login-btn" aria-label="ফিঙ্গারপ্রিন্ট দিয়ে লগইন">
                    <i class="fas fa-fingerprint"></i>
                </button>
                <span class="bio-login-label">ফিঙ্গারপ্রিন্ট</span>
            </div>
        </div>
    </form>

    <!-- নিচের লিংক সারি: ফিঙ্গারপ্রিন্ট সেটআপ + পাসওয়ার্ড রিসেট -->
    <div class="login-foot-links">
        <a href="index.php?next=bio_setup" class="foot-link bio-setup-link">
            <i class="fas fa-fingerprint"></i> ফিঙ্গারপ্রিন্ট সেট করুন
        </a>
        <a href="auth/otp_auth.php" class="foot-link">
            <i class="fas fa-shield-alt"></i> পাসওয়ার্ড রিসেট
        </a>
    </div>

</div>

<?php
/**
 * PARTIAL: profile_scripts.php
 * ─────────────────────────────────────────────────────────────
 * Profile page JavaScript only.
 * Covers:
 *   - switchTab(idx)             → .sk-tab-panel, .sk-tab-btn
 *   - togglePass(fieldId, btn)   → password show/hide
 *   - checkPasswordStrength(val) → #strengthFill, #strengthLabel
 *   - checkPasswordMatch()       → #matchMsg
 *   - pic input change handler   → #picPreviewWrap, #picUploadArea
 *   - removePicSelection()       → reset upload zone
 *   - handlePicDragOver/Leave/Drop → drag & drop upload
 * ─────────────────────────────────────────────────────────────
 * Required vars: none
 * Usage: include BEFORE </body>, after app_scripts.php
 */
?>

<!-- ══ PROFILE PAGE SCRIPTS ════════════════════════════════════
     Covers: tab switch, password toggle/strength, pic upload
══════════════════════════════════════════════════════════════ -->
<script>
/* ── Tab Switch ──────────────────────────────────────────────── */
function switchTab(targetIndex) {
    document.querySelectorAll('.sk-tab-panel').forEach(function(panel, index) {
        panel.classList.toggle('active', index === targetIndex);
    });
    document.querySelectorAll('.sk-tab-btn').forEach(function(button, index) {
        button.classList.toggle('active', index === targetIndex);
        button.setAttribute('aria-selected', String(index === targetIndex));
    });
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/* ── Password Show/Hide ──────────────────────────────────────── */
function togglePass(fieldId, toggleButton) {
    var passwordField = document.getElementById(fieldId);
    var isCurrentlyPassword = passwordField.type === 'password';
    passwordField.type = isCurrentlyPassword ? 'text' : 'password';
    toggleButton.querySelector('i').className = 'fas fa-eye' + (isCurrentlyPassword ? '-slash' : '');
}

/* ── Password Strength Checker ───────────────────────────────── */
function checkPasswordStrength(passwordValue) {
    var strengthScore = 0;
    if (passwordValue.length >= 6)            strengthScore++;
    if (passwordValue.length >= 10)           strengthScore++;
    if (/[A-Z]/.test(passwordValue))          strengthScore++;
    if (/[0-9]/.test(passwordValue))          strengthScore++;
    if (/[^A-Za-z0-9]/.test(passwordValue))  strengthScore++;

    var strengthLevels = [
        [0,   '',         ''],
        [20,  '#EF4444', 'দুর্বল'],
        [40,  '#F97316', 'মোটামুটি'],
        [60,  '#F59E0B', 'ভালো'],
        [80,  '#22C55E', 'শক্তিশালী'],
        [100, '#22D3EE', 'অত্যন্ত শক্তিশালী'],
    ];
    var currentLevel = strengthLevels[Math.min(strengthScore, 5)];

    var strengthBar   = document.getElementById('strengthFill');
    var strengthLabel = document.getElementById('strengthLabel');
    if (strengthBar)   { strengthBar.style.width = currentLevel[0] + '%'; strengthBar.style.background = currentLevel[1]; }
    if (strengthLabel) { strengthLabel.textContent = currentLevel[2]; strengthLabel.style.color = currentLevel[1]; }
}

/* ── Password Match Check ────────────────────────────────────── */
function checkPasswordMatch() {
    var newPassword     = document.getElementById('new_pass').value;
    var confirmPassword = document.getElementById('conf_pass').value;
    var matchMessage    = document.getElementById('matchMsg');
    if (!confirmPassword || !matchMessage) return;
    if (newPassword === confirmPassword) {
        matchMessage.style.color    = '#22C55E';
        matchMessage.textContent    = '✓ পাসওয়ার্ড মিলেছে';
    } else {
        matchMessage.style.color    = '#EF4444';
        matchMessage.textContent    = '✗ পাসওয়ার্ড মিলছে না';
    }
}

/* ── Profile Picture Upload Handlers ────────────────────────── */
document.addEventListener('DOMContentLoaded', function() {
    var picFileInput = document.getElementById('picInput');
    if (!picFileInput) return;

    picFileInput.addEventListener('change', function() {
        var selectedFile = this.files[0];
        if (!selectedFile) return;

        /* Client-side size guard (10 MB) */
        if (selectedFile.size > 10485760) {
            alert(
                '❌ ছবির সাইজ সর্বোচ্চ ১০ MB হতে পারবে।\n' +
                'নির্বাচিত: ' + Math.round(selectedFile.size / 1024 / 1024 * 100) / 100 + ' MB'
            );
            this.value = '';
            return;
        }

        /* FileReader preview */
        var fileReader = new FileReader();
        fileReader.onload = function(readerEvent) {
            document.getElementById('picPreviewImg').src = readerEvent.target.result;
            document.getElementById('picPreviewWrap').style.display = 'block';
            document.getElementById('picUploadArea').style.display  = 'none';
        };
        fileReader.readAsDataURL(selectedFile);

        /* File info row */
        document.getElementById('picFileName').textContent =
            selectedFile.name + ' (' + Math.round(selectedFile.size / 1024) + ' KB)';
        document.getElementById('picFileInfo').style.display   = 'block';
        document.getElementById('picSubmitBtn').style.display  = 'flex';
    });
});

/* ── Remove Picture Selection ────────────────────────────────── */
function removePicSelection() {
    document.getElementById('picInput').value       = '';
    document.getElementById('picPreviewWrap').style.display = 'none';
    document.getElementById('picUploadArea').style.display  = 'block';
    document.getElementById('picFileInfo').style.display    = 'none';
    document.getElementById('picSubmitBtn').style.display   = 'none';
}

/* ── Drag & Drop Handlers ────────────────────────────────────── */
function handlePicDragOver(event) {
    event.preventDefault();
    document.getElementById('picUploadArea').classList.add('dragging');
}
function handlePicDragLeave() {
    document.getElementById('picUploadArea').classList.remove('dragging');
}
function handlePicDrop(event) {
    event.preventDefault();
    document.getElementById('picUploadArea').classList.remove('dragging');
    var droppedFile = event.dataTransfer.files[0];
    if (droppedFile && droppedFile.type.startsWith('image/')) {
        var dataTransfer = new DataTransfer();
        dataTransfer.items.add(droppedFile);
        document.getElementById('picInput').files = dataTransfer.files;
        document.getElementById('picInput').dispatchEvent(new Event('change'));
    }
}
</script>

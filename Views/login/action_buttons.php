<?php
/**
 * VIEW: login/action_buttons.php
 * CSS: .action-buttons-row | .btn-wrapper | .btn-circle-3d
 *      .btn-privacy | .btn-gallery | .btn-fb | .btn-wa | .btn-dl | .btn-yt
 *      .btn-text-3d
 * FIX: সব বাটন একই structure — <div.btn-wrapper> → <a.btn-circle-3d> → icon
 *      Download বাটনে আগে <a> এর ভেতরে <div> ছিল → layout ভাঙত
 */
?>
<div class="action-buttons-row">

    <div class="btn-wrapper">
        <a href="https://www.facebook.com/share/1HATRCDFMu/"
           target="_blank" rel="noopener noreferrer"
           class="btn-circle-3d btn-fb" title="ফেসবুক">
            <i class="fab fa-facebook-f"></i>
        </a>
        <span class="btn-text-3d">ফেসবুক</span>
    </div>

    <div class="btn-wrapper">
        <a href="https://wa.me/8801821933259"
           target="_blank" rel="noopener noreferrer"
           class="btn-circle-3d btn-wa" title="হোয়াটসঅ্যাপ">
            <i class="fab fa-whatsapp"></i>
        </a>
        <span class="btn-text-3d">হোয়াটসঅ্যাপ</span>
    </div>
    <div class="btn-wrapper">
        <a href="https://whatsapp.com/channel/0029Vb6pIi48fewxPkHhCx2q"
           target="_blank" rel="noopener noreferrer"
           class="btn-circle-3d btn-yt" title="চ্যানেল">
            <i class="fab fa-whatsapp"></i>
        </a>
        <span class="btn-text-3d">CHANEL</span>
    </div>

</div>

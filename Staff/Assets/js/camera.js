/**
 * Staff/Assets/js/camera.js — Webcam capture helper (SADA KALO Staff System)
 *
 * প্রোফাইল/নোট ছবির জন্য shared ক্যামেরা লজিক।
 * প্রয়োজনীয় এলিমেন্ট আইডি:
 *   #webcam-view (video), #photo-canvas (canvas), #photo-preview (img),
 *   #captured_image (hidden input), #start-cam, #take-photo, #retake-photo, #file-upload
 *
 * পেজে inline ক্যামেরা কোড থাকলে এই ফাইল skip করে (SkCamera গার্ড)।
 */

if (typeof window.SkCamera === 'undefined') {
    window.SkCamera = (function () {
        var streamRef = null;

        function el(id) { return document.getElementById(id); }

        function start() {
            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } })
                .then(function (stream) {
                    streamRef = stream;
                    var video = el('webcam-view');
                    video.srcObject = stream;
                    video.style.display = 'block';
                    if (el('photo-preview')) el('photo-preview').style.display = 'none';
                    if (el('take-photo')) el('take-photo').style.display = 'inline-flex';
                    if (el('start-cam')) el('start-cam').style.display = 'none';
                    if (el('retake-photo')) el('retake-photo').style.display = 'none';
                })
                .catch(function () {
                    alert('ক্যামেরা পারমিশন পাওয়া যায়নি!');
                });
        }

        function capture() {
            var video = el('webcam-view');
            var canvas = el('photo-canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            var url = canvas.toDataURL('image/jpeg');
            if (el('captured_image')) el('captured_image').value = url;
            var preview = el('photo-preview');
            if (preview) { preview.src = url; preview.style.display = 'block'; }
            video.style.display = 'none';
            if (el('photo-preview-wrap')) el('photo-preview-wrap').style.display = 'block';
            if (el('take-photo')) el('take-photo').style.display = 'none';
            if (el('retake-photo')) el('retake-photo').style.display = 'inline-flex';
            stop();
            return url;
        }

        function stop() {
            if (streamRef) {
                streamRef.getTracks().forEach(function (t) { t.stop(); });
                streamRef = null;
            }
        }

        return { start: start, capture: capture, stop: stop };
    })();
}

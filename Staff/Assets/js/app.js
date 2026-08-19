/**
 * Staff/Assets/js/app.js — Shared UI helpers (SADA KALO Staff System)
 *
 * থিম টগল (dark/light) — সব পেজে ব্যবহৃত shared লজিক।
 * পেজগুলোর নিজস্ব inline স্ক্রিপ্ট থাকলে এই ফাইল safe-ভাবে skip করে
 * (typeof চেক থাকায় double-define হয় না)।
 */

/* Theme init (localStorage: sk_theme) */
(function () {
    var t = localStorage.getItem('sk_theme') || 'dark';
    document.documentElement.setAttribute('data-bs-theme', t);
    document.addEventListener('DOMContentLoaded', function () {
        var i = document.getElementById('themeIcon');
        if (i) i.className = (t === 'dark') ? 'fas fa-sun' : 'fas fa-moon';
    });
})();

/* Theme toggle */
if (typeof window.toggleTheme !== 'function') {
    window.toggleTheme = function () {
        var c = document.documentElement.getAttribute('data-bs-theme');
        var n = (c === 'dark') ? 'light' : 'dark';
        document.documentElement.setAttribute('data-bs-theme', n);
        localStorage.setItem('sk_theme', n);
        var i = document.getElementById('themeIcon');
        if (i) i.className = (n === 'dark') ? 'fas fa-sun' : 'fas fa-moon';
    };
}

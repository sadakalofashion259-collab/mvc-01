/* Sada Kalo Fashion — SMS Dashboard client */
(function () {
  'use strict';

  var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var ENDPOINT = window.location.pathname; // /Suppliers/Sms/

  function post(data) {
    var fd = new FormData();
    fd.append('csrf_token', CSRF);
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    return fetch(ENDPOINT, { method: 'POST', body: fd })
      .then(function (r) { return r.json(); });
  }

  function flash(el, ok, msg) {
    if (!el) return;
    el.textContent = (ok ? '✅ ' : '❌ ') + msg;
    el.className = 'fmsg ' + (ok ? 'ok' : 'err');
  }

  /* Per-supplier toggle */
  window.toggleSup = function (id, btn) {
    if (!btn) return;
    btn.disabled = true;
    post({ action: 'toggle_sms', sms_id: id })
      .then(function (d) {
        btn.disabled = false;
        if (!d.ok) { alert(d.msg || 'ত্রুটি!'); return; }
        var on = d.enabled === 1;
        btn.classList.toggle('on', on);
        var st = document.getElementById('st-' + id);
        if (st) { st.textContent = on ? 'চালু' : 'বন্ধ'; st.className = 'sup-st ' + (on ? 'on' : 'off'); }
      })
      .catch(function () { btn.disabled = false; alert('নেটওয়ার্ক সমস্যা!'); });
  };

  /* Supplier search */
  window.filterSup = function () {
    var q = (document.getElementById('supSearch').value || '').toLowerCase();
    document.querySelectorAll('#supList .sup').forEach(function (it) {
      it.style.display = (it.dataset.search || '').indexOf(q) > -1 ? 'flex' : 'none';
    });
  };

  /* Test SMS */
  window.sendTest = function () {
    var btn = document.getElementById('tBtn');
    var res = document.getElementById('tRes');
    var phone = (document.getElementById('tPhone').value || '').trim();
    var msg = (document.getElementById('tMsg').value || '').trim();
    if (!phone) { flash(res, false, 'নম্বর দিন।'); return; }
    var orig = btn.textContent;
    btn.disabled = true; btn.textContent = 'পাঠানো হচ্ছে...';
    post({ action: 'test_sms', phone: phone, message: msg })
      .then(function (d) {
        btn.disabled = false; btn.textContent = orig;
        flash(res, d.ok, d.msg || (d.ok ? 'পাঠানো হয়েছে' : 'ব্যর্থ'));
        if (d.ok) setTimeout(function () { location.reload(); }, 1500);
      })
      .catch(function () { btn.disabled = false; btn.textContent = orig; flash(res, false, 'নেটওয়ার্ক সমস্যা!'); });
  };

  /* Save gateway (admin) */
  window.saveGateway = function (btn) {
    var msg = document.getElementById('gwMsg');
    btn.disabled = true;
    post({
      action: 'save_gateway',
      sender: (document.getElementById('gwSender').value || '').trim(),
      trans_type: document.getElementById('gwType').value,
      enabled: document.getElementById('gwEnabled').value
    }).then(function (d) {
      btn.disabled = false;
      flash(msg, d.ok, d.msg || '');
      if (d.ok) setTimeout(function () { location.reload(); }, 1200);
    }).catch(function () { btn.disabled = false; flash(msg, false, 'নেটওয়ার্ক সমস্যা!'); });
  };

  /* Save template (admin) */
  window.saveTemplate = function (btn) {
    var card = btn.closest('.tpl');
    if (!card) return;
    var msg = card.querySelector('.tpl-msg');
    btn.disabled = true;
    post({
      action: 'save_template',
      tpl_id: card.dataset.id,
      title: (card.querySelector('.tpl-title').value || '').trim(),
      body: card.querySelector('.tpl-body').value,
      is_active: card.querySelector('.tpl-active').checked ? '1' : '0'
    }).then(function (d) {
      btn.disabled = false;
      flash(msg, d.ok, d.msg || '');
    }).catch(function () { btn.disabled = false; flash(msg, false, 'নেটওয়ার্ক সমস্যা!'); });
  };

})();

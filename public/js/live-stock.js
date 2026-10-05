/*
 * Stok realtime di halaman toko.
 * Menanyakan feed publik tiap beberapa detik (hanya saat tab terlihat) dan memperbarui
 * label stok serta tombol beli. Tanpa library; kalau gagal, halaman tetap benar saat dimuat ulang.
 */
(function () {
  var script = document.currentScript;
  if (!script || !document.querySelector('[data-stock-id]')) return;

  var url = script.dataset.feed;
  var cursor = parseInt(script.dataset.cursor || '0', 10);
  var delay = 4000;
  var timer = null;
  var busy = false;

  function apply(item) {
    document.querySelectorAll('[data-stock-id="' + item.id + '"]').forEach(function (el) {
      el.dataset.state = item.state;
      var text = el.querySelector('[data-stock-text]');
      if (text) text.textContent = item.label;
      el.classList.remove('flash');
      void el.offsetWidth; // mulai ulang animasi
      el.classList.add('flash');
    });

    document.querySelectorAll('[data-buy="' + item.id + '"]').forEach(function (btn) {
      if (btn.dataset.label === undefined) btn.dataset.label = btn.textContent.trim();
      btn.disabled = !item.available;
      btn.textContent = item.available ? btn.dataset.label : 'Stok habis';
    });
  }

  function schedule() {
    clearTimeout(timer);
    timer = setTimeout(tick, delay);
  }

  function tick() {
    if (busy || document.hidden) return schedule();
    busy = true;

    fetch(url + '?since=' + cursor, { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
      .then(function (data) {
        if (data.reload) { location.reload(); return; }
        (data.items || []).forEach(apply);
        cursor = data.cursor;
        delay = 4000;
      })
      .catch(function () { delay = Math.min(delay * 2, 30000); })
      .then(function () { busy = false; schedule(); });
  }

  document.addEventListener('visibilitychange', function () { if (!document.hidden) tick(); });
  schedule();
})();

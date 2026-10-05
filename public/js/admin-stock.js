/*
 * Pantauan stok realtime di admin: polling /admin/stok/feed tiap 3 detik saat tab terlihat.
 * Baris produk yang berubah ditandai kuning sebentar. Gagal = coba lagi pelan-pelan.
 */
(function () {
  var s = document.currentScript;
  if (!s) return;
  var url = s.dataset.feed, cursor = parseInt(s.dataset.cursor || '0', 10), delay = 3000, busy = false, timer;
  var dot = document.querySelector('[data-live]');

  function setLive(ok) { if (dot) dot.classList.toggle('is-off', !ok); }

  function apply(it) {
    var row = document.querySelector('tr[data-stock-id="' + it.id + '"]');
    if (!row) return;
    row.dataset.state = it.state;
    var q = row.querySelector('[data-qty]'); if (q) q.textContent = it.qty;
    var v = row.querySelector('[data-value]'); if (v) v.textContent = it.value;
    var b = row.querySelector('[data-label]'); if (b) b.textContent = it.label;
    row.classList.remove('flash-row'); void row.offsetWidth; row.classList.add('flash-row');
  }

  function tick() {
    if (busy || document.hidden) return next();
    busy = true;
    fetch(url + '?since=' + cursor, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
      .then(function (d) {
        if (d.reload) { location.reload(); return; }
        (d.items || []).forEach(apply);
        cursor = d.cursor; delay = 3000; setLive(true);
      })
      .catch(function () { delay = Math.min(delay * 2, 30000); setLive(false); })
      .then(function () { busy = false; next(); });
  }
  function next() { clearTimeout(timer); timer = setTimeout(tick, delay); }
  document.addEventListener('visibilitychange', function () { if (!document.hidden) tick(); });
  next();
})();

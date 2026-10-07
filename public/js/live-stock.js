/*
 * Stok realtime di halaman toko.
 * Menanyakan feed publik tiap beberapa detik (hanya saat tab terlihat) dan memperbarui
 * label stok serta tombol beli. Tanpa library; kalau gagal, halaman tetap benar saat dimuat ulang.
 */
(function () {
  var script = document.currentScript;
  if (!script || !document.querySelector('[data-stock-id],[data-stock-group]')) return;

  var url = script.dataset.feed;
  var cursor = parseInt(script.dataset.cursor || '0', 10);
  var delay = 4000;
  var timer = null;
  var busy = false;

  // Produk banyak variasi: label gabungan dihitung dari status tiap variasinya.
  var members = {}; // id variasi -> state
  var groups = [].slice.call(document.querySelectorAll('[data-stock-group]'));
  groups.forEach(function (el) {
    el._ids = [];
    (el.dataset.members || '').split(',').forEach(function (pair) {
      var p = pair.split(':');
      if (p[0]) { el._ids.push(p[0]); members[p[0]] = p[1]; }
    });
  });

  function groupState(el) {
    var live = el._ids.map(function (id) { return members[id]; }).filter(function (st) { return st && st !== 'out'; });
    if (!live.length) return 'out';
    return live.every(function (st) { return st === 'low'; }) ? 'low' : 'ok';
  }

  function applyGroups(id) {
    groups.forEach(function (el) {
      if (el._ids.indexOf(String(id)) === -1) return;
      var st = groupState(el);
      el.dataset.state = st;
      var text = el.querySelector('[data-stock-text]');
      if (text) text.textContent = st === 'out' ? 'Stok habis' : (st === 'low' ? 'Stok terbatas' : 'Stok tersedia');
      el.classList.remove('flash');
      void el.offsetWidth;
      el.classList.add('flash');
    });
  }

  function apply(item) {
    if (members.hasOwnProperty(String(item.id))) { members[String(item.id)] = item.state; applyGroups(item.id); }

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

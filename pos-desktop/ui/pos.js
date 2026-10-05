/*
 * Azola Pos (kasir) - keyboard-first. Satu file, tanpa library.
 * - Produk disimpan di IndexedDB, jadi kasir tetap jalan saat internet putus.
 * - Setiap penjualan punya uuid dan masuk antrean (outbox) lebih dulu, baru dikirim.
 *   Server menolak uuid ganda, jadi kirim ulang aman.
 * - Stok yang tampil = stok server - barang di antrean yang belum terkirim.
 */
(function () {
  'use strict';

  // ---------- util ----------
  var $ = function (id) { return document.getElementById(id); };
  var rp = function (n) { return (n < 0 ? '-' : '') + 'Rp' + Math.abs(Math.round(n)).toLocaleString('id-ID'); };
  var toMilli = function (v) { var n = parseFloat(String(v == null ? '0' : v).replace(',', '.')); return isFinite(n) ? Math.round(n * 1000) : 0; };
  var fromMilli = function (m) { var s = m < 0 ? '-' : ''; m = Math.abs(m); return s + Math.floor(m / 1000) + '.' + String(m % 1000).padStart(3, '0'); };
  var pretty = function (m) { return fromMilli(m).replace(/0+$/, '').replace(/\.$/, '').replace('.', ','); };
  var value = function (milli, price) { return Math.round(milli * price / 1000); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var uuid = function () { return (crypto.randomUUID ? crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) { var r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 3 | 8)).toString(16); })); };
  var inApp = !!(window.chrome && window.chrome.webview);

  // "50000", "50.000", "50rb", "50k" -> 50000
  function parseMoney(text) {
    var t = String(text || '').trim().toLowerCase().replace(/\s+/g, '');
    if (!t) return 0;
    var mult = 1;
    if (/(rb|k)$/.test(t)) { mult = 1000; t = t.replace(/(rb|k)$/, ''); }
    else if (/(jt|m)$/.test(t)) { mult = 1000000; t = t.replace(/(jt|m)$/, ''); }
    t = mult > 1 ? t.replace(',', '.') : t.replace(/[.,]/g, '');
    var n = parseFloat(t);
    return isFinite(n) ? Math.round(n * mult) : 0;
  }

  function toast(msg, bad) {
    var t = $('toast'); t.textContent = msg; t.className = 'toast' + (bad ? ' bad' : ''); t.hidden = false;
    clearTimeout(toast.t); toast.t = setTimeout(function () { t.hidden = true; }, bad ? 4500 : 2200);
  }
  var audio = null;
  function beep(ok) {
    if (!S.prefs.sound) return;
    try {
      audio = audio || new (window.AudioContext || window.webkitAudioContext)();
      var o = audio.createOscillator(), g = audio.createGain();
      o.type = 'square'; o.frequency.value = ok ? 1000 : 180; g.gain.value = 0.04;
      o.connect(g); g.connect(audio.destination); o.start(); o.stop(audio.currentTime + (ok ? 0.06 : 0.22));
    } catch (e) { /* tanpa suara */ }
  }

  // ---------- IndexedDB ----------
  var dbp = new Promise(function (res, rej) {
    var r = indexedDB.open('azola-pos', 2);
    r.onupgradeneeded = function () {
      var d = r.result;
      ['products', 'outbox', 'sales', 'holds'].forEach(function (n) {
        if (!d.objectStoreNames.contains(n)) d.createObjectStore(n, { keyPath: n === 'products' ? 'id' : (n === 'holds' ? 'id' : 'uuid') });
      });
      if (!d.objectStoreNames.contains('meta')) d.createObjectStore('meta');
    };
    r.onsuccess = function () { res(r.result); };
    r.onerror = function () { rej(r.error); };
  });
  function tx(store, mode, fn) {
    return dbp.then(function (d) {
      return new Promise(function (res, rej) {
        var t = d.transaction(store, mode), s = t.objectStore(store), out = fn(s);
        t.oncomplete = function () { res(out && 'result' in out ? out.result : undefined); };
        t.onerror = function () { rej(t.error); };
      });
    });
  }
  var idb = {
    all: function (s) { return tx(s, 'readonly', function (st) { return st.getAll(); }); },
    get: function (s, k) { return tx(s, 'readonly', function (st) { return st.get(k); }); },
    put: function (s, v, k) { return tx(s, 'readwrite', function (st) { return st.put(v, k); }); },
    del: function (s, k) { return tx(s, 'readwrite', function (st) { return st.delete(k); }); },
    clear: function (s) { return tx(s, 'readwrite', function (st) { return st.clear(); }); },
    putMany: function (s, arr) { return tx(s, 'readwrite', function (st) { arr.forEach(function (v) { st.put(v); }); }); }
  };

  // ---------- state ----------
  var S = {
    session: null, cfg: { name: 'Azola Pos', address: '', phone: '', tax_rate: 0 },
    prefs: { footer: 'Terima kasih', printer: '', cols: 32, drawer: false, auto: inApp, sound: true },
    products: new Map(), cats: new Map(), outbox: [], holds: [],
    cart: [],            // [{id, milli, disc}]
    sel: 0,              // indeks baris terpilih
    odisc: 0,            // diskon transaksi (Rp)
    sugg: [], suggIdx: 0,
    cursor: null, since: null, online: false, syncing: false, lastReceipt: null
  };

  function loadLocal() {
    try { S.session = JSON.parse(localStorage.getItem('azp.session') || 'null'); } catch (e) { S.session = null; }
    try { Object.assign(S.prefs, JSON.parse(localStorage.getItem('azp.prefs') || '{}')); } catch (e) { /* abaikan */ }
    try { Object.assign(S.cfg, JSON.parse(localStorage.getItem('azp.cfg') || '{}')); } catch (e) { /* abaikan */ }
  }
  var saveSession = function () { localStorage.setItem('azp.session', JSON.stringify(S.session)); };
  var savePrefs = function () { localStorage.setItem('azp.prefs', JSON.stringify(S.prefs)); };

  // ---------- API ----------
  function api(method, path, body) {
    var ctl = new AbortController(), timer = setTimeout(function () { ctl.abort(); }, 15000);
    return fetch(S.session.url.replace(/\/+$/, '') + '/api/v1/' + path, {
      method: method, signal: ctl.signal,
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + S.session.token },
      body: body ? JSON.stringify(body) : undefined
    }).then(function (r) {
      clearTimeout(timer);
      return r.json().catch(function () { return {}; }).then(function (j) { return { status: r.status, body: j }; });
    }, function () { clearTimeout(timer); var err = new Error('network'); err.network = true; throw err; });
  }
  function authError() { var e = new Error('auth'); e.auth = true; return e; }

  // ---------- stok ----------
  var reservedMap = new Map();
  function reserved() {
    var m = new Map();
    S.outbox.forEach(function (o) {
      if (o.state === 'failed') return;
      o.payload.items.forEach(function (it) { m.set(it.product_id, (m.get(it.product_id) || 0) + toMilli(it.qty)); });
    });
    return m;
  }
  function avail(p) { return p.sMilli - (reservedMap.get(p.id) || 0); }
  function stockState(p) { var a = avail(p), min = toMilli(p.min_stock) || 5000; return a <= 0 ? 'out' : (a <= min ? 'low' : 'ok'); }

  // ---------- keranjang ----------
  function lineGross(l) { var p = S.products.get(l.id); return p ? value(l.milli, p.price) : 0; }
  function totals() {
    var sub = 0, ld = 0;
    S.cart.forEach(function (l) { var g = lineGross(l); sub += g; ld += Math.min(l.disc || 0, g); });
    var od = Math.max(0, Math.min(sub - ld, S.odisc || 0));
    var net = sub - ld - od, tax = Math.round(net * (S.cfg.tax_rate || 0) / 100);
    return { sub: sub, ld: ld, od: od, tax: tax, total: net + tax, count: S.cart.length };
  }
  function selLine() { return S.cart[S.sel] || null; }

  function addProduct(p, milli) {
    milli = milli || 1000;
    var idx = -1; S.cart.forEach(function (l, i) { if (l.id === p.id) idx = i; });
    var have = idx >= 0 ? S.cart[idx].milli : 0, max = avail(p);
    if (max <= 0) { beep(false); toast(p.name + ': stok habis.', true); return false; }
    if (have + milli > max) {
      beep(false); toast(p.name + ': stok hanya ' + pretty(max) + ' ' + p.unit + '.', true);
      if (have >= max) return false;
      milli = max - have;
    } else beep(true);
    if (idx >= 0) S.cart[idx].milli += milli; else { S.cart.push({ id: p.id, milli: milli, disc: 0 }); idx = S.cart.length - 1; }
    S.sel = idx; renderCart(idx);
    return true;
  }
  function removeLine(i) { S.cart.splice(i, 1); S.sel = Math.max(0, Math.min(S.sel, S.cart.length - 1)); renderCart(); }

  // ---------- render ----------
  function renderCart(flashIdx) {
    S.cart = S.cart.filter(function (l) { return S.products.has(l.id); });
    if (S.sel >= S.cart.length) S.sel = Math.max(0, S.cart.length - 1);
    var h = '';
    S.cart.forEach(function (l, i) {
      var p = S.products.get(l.id), g = lineGross(l), over = l.milli > avail(p);
      h += '<tr data-i="' + i + '" class="' + (i === S.sel ? 'is-sel' : '') + (i === flashIdx ? ' flash' : '') + '">' +
        '<td class="c-no">' + (i + 1) + '</td><td class="c-sku">' + esc(p.sku) + '</td>' +
        '<td class="nm">' + esc(p.name) + (over ? '<span class="warn">Stok tersisa ' + pretty(avail(p)) + ' ' + esc(p.unit) + '. Kurangi jumlahnya.</span>' : '') + '</td>' +
        '<td class="c-qty num">' + pretty(l.milli) + ' <small class="muted">' + esc(p.unit) + '</small></td>' +
        '<td class="c-price num">' + rp(p.price) + '</td>' +
        '<td class="c-disc num">' + (l.disc ? '-' + rp(Math.min(l.disc, g)) : '') + '</td>' +
        '<td class="c-sub num">' + rp(g - Math.min(l.disc || 0, g)) + '</td></tr>';
    });
    $('rows').innerHTML = h;
    $('cartEmpty').hidden = S.cart.length > 0;
    $('cartTable').hidden = S.cart.length === 0;
    var t = totals(), blocked = S.cart.some(function (l) { return l.milli > avail(S.products.get(l.id)); });
    $('sTotal').textContent = rp(t.total); $('sSub').textContent = rp(t.sub); $('sCount').textContent = t.count;
    $('sLD').textContent = '-' + rp(t.ld); $('sOD').textContent = '-' + rp(t.od); $('sTax').textContent = rp(t.tax);
    $('rowLD').hidden = !t.ld; $('rowOD').hidden = !t.od; $('taxRow').hidden = !(S.cfg.tax_rate > 0);
    $('taxPct').textContent = '(' + S.cfg.tax_rate + '%)';
    $('btnPay').disabled = !S.cart.length || blocked;
    renderSel();
    var tr = $('rows').querySelector('tr.is-sel'); if (tr && tr.scrollIntoView) tr.scrollIntoView({ block: 'nearest' });
  }
  function renderSel() {
    var l = selLine(), el = $('selInfo');
    if (!l) { el.innerHTML = '<span class="muted">Belum ada baris terpilih.</span>'; return; }
    var p = S.products.get(l.id), st = stockState(p);
    el.innerHTML = '<b>' + esc(p.name) + '</b>' + rp(p.price) + ' / ' + esc(p.unit) +
      '<br><span class="st" data-s="' + st + '">Stok tersedia ' + pretty(Math.max(0, avail(p))) + ' ' + esc(p.unit) + '</span>';
  }
  function renderStatus() {
    $('status').className = 'status' + (S.syncing ? ' is-sync' : (S.online ? '' : ' is-off'));
    $('statusText').textContent = S.syncing ? 'Menyinkronkan' : (S.online ? 'Tersambung' : 'Offline, transaksi disimpan dulu');
    $('queueN').textContent = S.outbox.length;
    $('storeName').textContent = S.cfg.name || 'Azola Pos';
    $('who').textContent = S.session ? S.session.user.name : '';
    $('holdN').textContent = S.holds.length; $('holdN').hidden = !S.holds.length;
  }
  function refreshAll() { reservedMap = reserved(); renderCart(); renderStatus(); if (S.sugg.length) renderSugg(); }
  function tickClock() { var d = new Date(); $('clock').textContent = d.toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short' }) + ' ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0'); }

  // ---------- kolom scan + saran ----------
  function matches(p, q) { return (p.name + ' ' + p.sku + ' ' + (p.barcode || '')).toLowerCase().indexOf(q) !== -1; }
  function parseScan(text) {
    var m = String(text).trim().match(/^([\d.,]+)\s*[*xX]\s*(.+)$/);
    return m ? { milli: toMilli(m[1]), q: m[2].trim() } : { milli: 1000, q: String(text).trim() };
  }
  function exactMatch(q) {
    var l = q.toLowerCase(), hit = null;
    S.products.forEach(function (p) { if (p.is_active && ((p.barcode && p.barcode.toLowerCase() === l) || p.sku.toLowerCase() === l)) hit = p; });
    return hit;
  }
  function search(q) {
    var l = q.toLowerCase(), out = [];
    S.products.forEach(function (p) {
      if (!p.is_active || !matches(p, l)) return;
      var name = p.name.toLowerCase(), rank = name.indexOf(l) === 0 || p.sku.toLowerCase().indexOf(l) === 0 ? 0 : 1;
      out.push({ p: p, rank: rank });
    });
    out.sort(function (a, b) { return a.rank - b.rank || a.p.name.localeCompare(b.p.name, 'id'); });
    return out.slice(0, 8).map(function (x) { return x.p; });
  }
  function updateSugg() {
    var q = parseScan($('scan').value).q;
    S.sugg = q ? search(q.toLowerCase()) : []; S.suggIdx = 0; renderSugg();
  }
  function renderSugg() {
    var ul = $('sugg'), q = parseScan($('scan').value).q;
    if (!q) { ul.hidden = true; return; }
    ul.hidden = false;
    ul.innerHTML = S.sugg.length ? S.sugg.map(function (p, i) {
      var a = avail(p), st = stockState(p);
      return '<li role="option" data-i="' + i + '" class="' + (i === S.suggIdx ? 'is-sel' : '') + '"><span>' + esc(p.name) + '<small>' + esc(p.sku) + (p.barcode ? ' &middot; ' + esc(p.barcode) : '') + '</small></span>' +
        '<span class="stk" data-s="' + st + '">' + (a <= 0 ? 'Habis' : pretty(a) + ' ' + esc(p.unit)) + '</span><span class="pr">' + rp(p.price) + '</span></li>';
    }).join('') : '<li class="none">Tidak ditemukan. Cek kode atau ketik nama lain.</li>';
    var sel = ul.querySelector('.is-sel'); if (sel && sel.scrollIntoView) sel.scrollIntoView({ block: 'nearest' });
  }
  function clearScan() { $('scan').value = ''; S.sugg = []; $('sugg').hidden = true; }
  function submitScan() {
    var raw = $('scan').value;
    if (!raw.trim()) { if (S.cart.length) openPay(); return; }
    var sc = parseScan(raw);
    if (sc.milli <= 0) { beep(false); toast('Jumlah tidak valid.', true); return; }
    var pick = exactMatch(sc.q);
    if (!pick) { var list = S.sugg.length ? S.sugg : search(sc.q.toLowerCase()); pick = list[S.suggIdx] || null; }
    if (!pick) { beep(false); toast('Barang "' + sc.q + '" tidak ditemukan.', true); return; }
    addProduct(pick, sc.milli); clearScan();
  }
  function focusScan(select) { var s = $('scan'); s.focus(); if (select) s.select(); }

  // ---------- jendela kecil: tanya / konfirmasi ----------
  function ask(o) {
    return new Promise(function (resolve) {
      var d = $('dAsk'), inp = $('aInput'), done = false;
      $('aTitle').textContent = o.title; $('aMsg').textContent = o.msg || '';
      inp.hidden = !!o.confirm; inp.value = o.value == null ? '' : o.value; inp.placeholder = o.placeholder || '';
      $('aOk').innerHTML = (o.ok || 'OK') + ' <kbd>Enter</kbd>';
      function finish(val) { if (done) return; done = true; $('askForm').removeEventListener('submit', onSubmit); d.removeEventListener('close', onClose); if (d.open) d.close(); resolve(val); }
      function onSubmit(e) { e.preventDefault(); finish(o.confirm ? true : inp.value); }
      function onClose() { finish(o.confirm ? false : null); }
      $('askForm').addEventListener('submit', onSubmit); d.addEventListener('close', onClose);
      d.showModal();
      if (o.confirm) $('aOk').focus(); else { inp.focus(); inp.select(); }
    });
  }

  // ---------- aksi baris / transaksi ----------
  function actQty() {
    var l = selLine(); if (!l) { toast('Belum ada baris.', true); return; }
    var p = S.products.get(l.id);
    ask({ title: 'Jumlah', msg: p.name + ' (' + p.unit + '). Isi 0 untuk menghapus.', value: pretty(l.milli), ok: 'Ubah' }).then(function (v) {
      if (v == null) return;
      var m = toMilli(v);
      if (m <= 0) { removeLine(S.sel); return; }
      if (m > avail(p)) { beep(false); toast('Stok ' + p.name + ' hanya ' + pretty(avail(p)) + ' ' + p.unit + '.', true); m = Math.max(0, avail(p)); if (m <= 0) return; }
      l.milli = m; renderCart(S.sel);
    });
  }
  function discAmount(text, base) { // "5000", "5k", atau "10%"
    var t = String(text || '').trim();
    if (!t) return 0;
    if (t.slice(-1) === '%') { var pc = parseFloat(t.slice(0, -1).replace(',', '.')); return isFinite(pc) ? Math.round(base * Math.min(100, Math.max(0, pc)) / 100) : 0; }
    return Math.min(base, parseMoney(t));
  }
  function actLineDisc() {
    var l = selLine(); if (!l) { toast('Belum ada baris.', true); return; }
    var p = S.products.get(l.id), g = lineGross(l);
    ask({ title: 'Diskon baris', msg: p.name + ' (' + rp(g) + '). Isi Rp atau persen, contoh 5000 atau 10%. Kosongkan untuk menghapus.', value: l.disc ? String(l.disc) : '', ok: 'Terapkan', placeholder: '5000 atau 10%' }).then(function (v) {
      if (v == null) return;
      l.disc = discAmount(v, g); renderCart(S.sel);
    });
  }
  function actOrderDisc() {
    if (!S.cart.length) { toast('Belum ada barang.', true); return; }
    var t = totals(), base = t.sub - t.ld;
    ask({ title: 'Diskon transaksi', msg: 'Dari ' + rp(base) + '. Isi Rp atau persen. Kosongkan untuk menghapus.', value: S.odisc ? String(S.odisc) : '', ok: 'Terapkan', placeholder: '10000 atau 5%' }).then(function (v) {
      if (v == null) return;
      S.odisc = discAmount(v, base); renderCart();
    });
  }
  function actClear() {
    if (!S.cart.length) return;
    ask({ title: 'Kosongkan transaksi?', msg: S.cart.length + ' baris akan dihapus.', confirm: true, ok: 'Ya, kosongkan' }).then(function (ok) { if (ok) { S.cart = []; S.odisc = 0; S.sel = 0; renderCart(); } });
  }
  function actHold() {
    if (!S.cart.length) { toast('Tidak ada transaksi untuk ditahan.', true); return; }
    var h = { id: uuid(), at: new Date().toISOString(), cart: S.cart, odisc: S.odisc, total: totals().total, count: S.cart.length };
    S.holds.push(h);
    idb.put('holds', h).then(function () { S.cart = []; S.odisc = 0; S.sel = 0; refreshAll(); toast('Transaksi ditahan. F7 untuk memanggil.'); });
  }
  function actRecall() {
    if (!S.holds.length) { toast('Tidak ada transaksi yang ditahan.', true); return; }
    openList({
      title: 'Transaksi ditahan', sub: 'Pilih dengan panah, Enter untuk memanggil, Del untuk menghapus.', hint: '<kbd>Enter</kbd> panggil &nbsp; <kbd>Del</kbd> hapus',
      items: function () { return S.holds.slice().reverse().map(function (h) { return { key: h.id, html: '<span>' + new Date(h.at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) + ' &middot; ' + h.count + ' baris<small>' + rp(h.total) + '</small></span>' }; }); },
      enter: function (key, close) {
        var h = S.holds.filter(function (x) { return x.id === key; })[0]; if (!h) return;
        if (S.cart.length) { var cur = { id: uuid(), at: new Date().toISOString(), cart: S.cart, odisc: S.odisc, total: totals().total, count: S.cart.length }; S.holds.push(cur); idb.put('holds', cur); }
        S.holds = S.holds.filter(function (x) { return x.id !== key; }); idb.del('holds', key);
        S.cart = h.cart; S.odisc = h.odisc || 0; S.sel = 0; close(); refreshAll();
      },
      del: function (key, rerender) { S.holds = S.holds.filter(function (x) { return x.id !== key; }); idb.del('holds', key); renderStatus(); rerender(); }
    });
  }
  function actLast() {
    idb.all('sales').then(function (rows) {
      rows.sort(function (a, b) { return a.at < b.at ? 1 : -1; });
      if (!rows.length) { toast('Belum ada transaksi di perangkat ini.', true); return; }
      showReceipt(rows[0], false);
    });
  }
  function actHistory() {
    idb.all('sales').then(function (rows) {
      rows.sort(function (a, b) { return a.at < b.at ? 1 : -1; });
      if (rows.length > 200) rows.slice(200).forEach(function (s) { if (s.synced) idb.del('sales', s.uuid); });
      var byId = {}; rows.forEach(function (s) { byId[s.uuid] = s; });
      openList({
        title: 'Riwayat terakhir', sub: 'Enter untuk melihat dan mencetak ulang struk.', hint: '<kbd>Enter</kbd> buka struk',
        items: function () { return rows.slice(0, 40).map(function (s) { return { key: s.uuid, html: '<span>' + new Date(s.at).toLocaleString('id-ID', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: 'short' }) + ' &middot; ' + rp(s.total) + '<small>' + (s.number || 'belum terkirim') + ' &middot; ' + esc(s.method) + '</small></span>' }; }); },
        enter: function (key, close) { close(); showReceipt(byId[key], false); }
      });
    });
  }
  function actQueue() {
    openList({
      title: 'Antrean kirim', sub: 'Transaksi yang belum sampai ke server. Stok dihitung dengan memperhitungkan antrean ini.', hint: '<kbd>Enter</kbd> coba kirim &nbsp; <kbd>Del</kbd> hapus (hanya yang ditolak)',
      items: function () {
        return S.outbox.length ? S.outbox.map(function (o) {
          return { key: o.uuid, html: '<span>' + new Date(o.at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) + ' &middot; ' + o.payload.items.length + ' barang' +
            (o.state === 'failed' ? '<small class="bad">Ditolak: ' + esc(o.error) + '</small>' : '<small>Menunggu koneksi</small>') + '</span>' };
        }) : [{ key: '', html: '<span class="muted">Tidak ada antrean. Semua transaksi sudah terkirim.</span>' }];
      },
      enter: function (key, close, rerender) {
        S.outbox.forEach(function (o) { if (o.state === 'failed') { o.state = 'pending'; idb.put('outbox', o); } });
        reservedMap = reserved(); tick(true); toast('Mengirim...'); rerender();
      },
      del: function (key, rerender) {
        var o = S.outbox.filter(function (x) { return x.uuid === key; })[0];
        if (!o || o.state !== 'failed') { toast('Hanya transaksi yang ditolak server yang bisa dihapus.', true); return; }
        ask({ title: 'Hapus transaksi ini?', msg: 'Transaksi tidak akan tercatat di pembukuan. Stok di server tidak berubah.', confirm: true, ok: 'Ya, hapus' }).then(function (ok) {
          if (!ok) return;
          S.outbox = S.outbox.filter(function (x) { return x.uuid !== key; }); idb.del('outbox', key); refreshAll(); actQueue();
        });
      }
    });
  }

  // daftar yang bisa dinavigasi dengan panah
  var listState = null;
  function openList(cfg) {
    var d = $('dList'), idx = 0;
    $('lTitle').textContent = cfg.title; $('lSub').textContent = cfg.sub || ''; $('lHint').innerHTML = cfg.hint || '';
    function render() {
      var items = cfg.items();
      if (idx >= items.length) idx = Math.max(0, items.length - 1);
      $('lItems').innerHTML = items.map(function (it, i) { return '<li role="option" data-k="' + esc(it.key) + '" data-i="' + i + '" class="' + (i === idx ? 'is-sel' : '') + '">' + it.html + '</li>'; }).join('');
      var s = $('lItems').querySelector('.is-sel'); if (s && s.scrollIntoView) s.scrollIntoView({ block: 'nearest' });
      listState.items = items;
    }
    listState = { cfg: cfg, move: function (n) { idx = Math.max(0, Math.min(listState.items.length - 1, idx + n)); render(); }, render: render,
      enter: function () { var it = listState.items[idx]; if (it) cfg.enter(it.key, function () { d.close(); }, render); },
      del: function () { var it = listState.items[idx]; if (it && it.key && cfg.del) cfg.del(it.key, render); },
      pick: function (i) { idx = i; listState.enter(); } };
    listState.items = []; render();
    if (!d.open) d.showModal();
  }

  // ---------- pembayaran ----------
  var payMethods = [['cash', 'Tunai'], ['transfer', 'Transfer'], ['qris', 'QRIS'], ['debit', 'Debit']];
  function payMethod() { var r = document.querySelector('input[name="pm"]:checked'); return r ? r.value : 'cash'; }
  function setMethod(m) { var r = document.querySelector('input[name="pm"][value="' + m + '"]'); if (r) { r.checked = true; payMethodChanged(); } }
  function paidValue() { return parseMoney($('pPaid').value); }
  function openPay() {
    if (!S.cart.length || $('btnPay').disabled) return;
    var t = totals();
    $('pTotal').textContent = rp(t.total);
    $('pMethods').innerHTML = payMethods.map(function (m, i) { return '<label><kbd>F' + (i + 1) + '</kbd><input type="radio" name="pm" value="' + m[0] + '"' + (i === 0 ? ' checked' : '') + '>' + m[1] + '</label>'; }).join('');
    $('pName').value = '';
    var quick = [50000, 100000, 200000, 500000].filter(function (v) { return v > t.total; }).slice(0, 3);
    $('pQuick').innerHTML = '<button type="button" data-v="' + t.total + '"><kbd>F5</kbd>Uang pas</button>' + quick.map(function (v, i) { return '<button type="button" data-v="' + v + '"><kbd>F' + (6 + i) + '</kbd>' + rp(v) + '</button>'; }).join('');
    $('pPaid').value = t.total; updateChange();
    $('dPay').showModal(); $('pPaid').focus(); $('pPaid').select();
  }
  function payMethodChanged() { if (payMethod() !== 'cash') $('pPaid').value = totals().total; updateChange(); }
  function updateChange() {
    var t = totals(), paid = paidValue(), el = $('pChange');
    if (paid >= t.total) { el.className = 'dlg__line' + (payMethod() === 'cash' && paid > t.total ? ' ok' : ''); el.textContent = payMethod() === 'cash' && paid > t.total ? 'Kembalian ' + rp(paid - t.total) : ''; }
    else { el.className = 'dlg__line bad'; el.textContent = 'Kurang ' + rp(t.total - paid) + ' (dicatat sebagai piutang)'; }
  }
  function commitSale() {
    var t = totals(), paid = paidValue(), method = payMethod();
    var items = S.cart.map(function (l) { var it = { product_id: l.id, qty: fromMilli(l.milli) }; var d = Math.min(l.disc || 0, lineGross(l)); if (d) it.discount = d; return it; });
    var id = uuid(), now = new Date().toISOString();
    var payload = { uuid: id, items: items, order_discount: t.od, payment_method: method, paid_total: Math.min(paid, t.total), ordered_at: now };
    var name = $('pName').value.trim(); if (name) payload.customer_name = name;

    var sale = {
      uuid: id, at: now, number: null, synced: false, method: method, cashier: S.session.user.name, customer: name,
      sub: t.sub, disc: t.ld + t.od, tax: t.tax, total: t.total, paid: paid, change: Math.max(0, paid - t.total),
      lines: S.cart.map(function (l) { var p = S.products.get(l.id), g = lineGross(l), d = Math.min(l.disc || 0, g); return { name: p.name, unit: p.unit, milli: l.milli, price: p.price, disc: d, total: g - d }; })
    };
    var entry = { uuid: id, payload: payload, state: 'pending', error: null, at: now };
    S.outbox.push(entry);
    return Promise.all([idb.put('outbox', entry), idb.put('sales', sale)]).then(function () {
      S.cart = []; S.odisc = 0; S.sel = 0; $('dPay').close();
      refreshAll(); showReceipt(sale, true); tick(true);
    });
  }

  // ---------- struk ----------
  function wrap(text, cols) {
    var out = [], cur = '';
    String(text).split(/\s+/).forEach(function (w) {
      while (w.length > cols) { if (cur) { out.push(cur); cur = ''; } out.push(w.slice(0, cols)); w = w.slice(cols); }
      if (!cur) cur = w; else if ((cur + ' ' + w).length <= cols) cur += ' ' + w; else { out.push(cur); cur = w; }
    });
    if (cur) out.push(cur);
    return out.length ? out : [''];
  }
  function receiptLines(sale, cols) {
    var L = [], line = function (a, b) { var gap = cols - a.length - b.length; return gap > 0 ? a + ' '.repeat(gap) + b : a + ' ' + b; };
    var center = function (s) { s = s.slice(0, cols); return ' '.repeat(Math.max(0, Math.floor((cols - s.length) / 2))) + s; };
    var rule = '-'.repeat(cols), d = new Date(sale.at), pad2 = function (n) { return String(n).padStart(2, '0'); };
    L.push(center(S.cfg.name || 'Azola Pos'));
    if (S.cfg.address) wrap(S.cfg.address, cols).forEach(function (s) { L.push(center(s)); });
    if (S.cfg.phone) L.push(center(S.cfg.phone));
    L.push(rule);
    L.push(line(pad2(d.getDate()) + '/' + pad2(d.getMonth() + 1) + '/' + d.getFullYear() + ' ' + pad2(d.getHours()) + ':' + pad2(d.getMinutes()), 'Kasir ' + (sale.cashier || '').split(' ')[0]));
    L.push('No ' + (sale.number || 'L-' + sale.uuid.slice(0, 8).toUpperCase()));
    if (sale.customer) L.push('Pelanggan ' + sale.customer);
    L.push(rule);
    sale.lines.forEach(function (l) {
      wrap(l.name, cols).forEach(function (s) { L.push(s); });
      L.push(line('  ' + pretty(l.milli) + ' ' + l.unit + ' x ' + l.price.toLocaleString('id-ID'), (l.total + (l.disc || 0)).toLocaleString('id-ID')));
      if (l.disc) L.push(line('  Diskon', '-' + l.disc.toLocaleString('id-ID')));
    });
    L.push(rule);
    L.push(line('Subtotal', rp(sale.sub)));
    if (sale.disc) L.push(line('Diskon', '-' + rp(sale.disc)));
    if (sale.tax) L.push(line('Pajak', rp(sale.tax)));
    L.push(line('TOTAL', rp(sale.total)));
    L.push(line('Bayar (' + sale.method + ')', rp(sale.paid)));
    if (sale.paid >= sale.total) { if (sale.change) L.push(line('Kembali', rp(sale.change))); } else L.push(line('Kurang', rp(sale.total - sale.paid)));
    L.push(rule);
    if (S.prefs.footer) wrap(S.prefs.footer, cols).forEach(function (s) { L.push(center(s)); });
    return L;
  }
  function showReceipt(sale, fresh) {
    S.lastReceipt = sale;
    var due = sale.paid < sale.total;
    $('rDone').innerHTML = fresh
      ? (due ? '<small>Transaksi tersimpan. Sisa tagihan</small><b class="due">' + rp(sale.total - sale.paid) + '</b>' : (sale.change ? '<small>Kembalian</small><b>' + rp(sale.change) + '</b>' : '<small>Transaksi tersimpan</small><b>Lunas</b>'))
      : '<small>Struk ' + (sale.number || 'belum terkirim') + '</small><b>' + rp(sale.total) + '</b>';
    $('rText').textContent = receiptLines(sale, S.prefs.cols).join('\n');
    if (!$('dReceipt').open) $('dReceipt').showModal();
    $('rNew').focus();
    if (fresh && S.prefs.auto) printReceipt();
  }
  function printReceipt() {
    var sale = S.lastReceipt; if (!sale) return;
    if (inApp) window.chrome.webview.postMessage({ type: 'print', printer: S.prefs.printer, cols: S.prefs.cols, drawer: !!S.prefs.drawer, lines: receiptLines(sale, S.prefs.cols) });
    else window.print();
  }

  // ---------- sinkronisasi ----------
  function mapProduct(p) { p.sMilli = toMilli(p.stock); p.category_name = S.cats.get(p.category_id) || ''; return p; }
  function fetchAllProducts() {
    var all = [], page = 1;
    return api('GET', 'categories').then(function (r) {
      if (r.status === 401) throw authError();
      S.cats = new Map((r.body.data || []).map(function (c) { return [c.id, c.name]; }));
      function next() {
        return api('GET', 'products?per_page=1000&page=' + page).then(function (r2) {
          if (r2.status === 401) throw authError();
          if (r2.status !== 200) throw new Error('products ' + r2.status);
          all = all.concat(r2.body.data || []);
          if (r2.body.meta && page < r2.body.meta.last_page) { page++; return next(); }
          return r2.body;
        });
      }
      return next();
    }).then(function (last) {
      var list = all.map(mapProduct);
      S.products = new Map(list.map(function (p) { return [p.id, p]; }));
      S.since = last.server_time; S.cursor = last.stock_cursor;
      return idb.clear('products').then(function () { return idb.putMany('products', list); })
        .then(function () { return idb.put('meta', { since: S.since, cursor: S.cursor, cats: Array.from(S.cats) }, 'sync'); });
    });
  }
  function pollStock() {
    return api('GET', 'sync/stock' + (S.cursor != null ? '?since=' + S.cursor : '')).then(function (r) {
      if (r.status === 401) throw authError();
      if (r.status !== 200) throw new Error('stock ' + r.status);
      var b = r.body;
      if (b.full && S.cursor != null) return fetchAllProducts();
      var changed = [];
      (b.items || []).forEach(function (it) { var p = S.products.get(it.id); if (!p) return; p.stock = it.stock; p.sMilli = toMilli(it.stock); p.is_active = it.is_active; changed.push(p); });
      S.cursor = b.cursor;
      if (changed.length) idb.putMany('products', changed);
    });
  }
  function pollMaster() {
    if (!S.since) return Promise.resolve();
    return api('GET', 'products?per_page=1000&updated_since=' + encodeURIComponent(S.since)).then(function (r) {
      if (r.status !== 200) return;
      var changed = [];
      (r.body.data || []).forEach(function (p) { var q = mapProduct(p); S.products.set(q.id, q); changed.push(q); });
      S.since = r.body.server_time;
      if (changed.length) idb.putMany('products', changed);
    });
  }
  function flushOutbox() {
    var chain = Promise.resolve();
    S.outbox.filter(function (o) { return o.state !== 'failed'; }).forEach(function (o) {
      chain = chain.then(function () {
        return api('POST', 'orders', o.payload).then(function (r) {
          if (r.status === 200 || r.status === 201) {
            var d = r.body.data || {};
            S.outbox = S.outbox.filter(function (x) { return x.uuid !== o.uuid; });
            return idb.del('outbox', o.uuid).then(function () {
              return idb.get('sales', o.uuid).then(function (sale) { if (sale) { sale.number = d.number; sale.synced = true; return idb.put('sales', sale); } });
            });
          }
          if (r.status === 401) throw authError();
          if (r.status === 422) { o.state = 'failed'; o.error = (r.body && r.body.message) || 'Ditolak server'; beep(false); return idb.put('outbox', o); }
          throw new Error('order ' + r.status);
        });
      });
    });
    return chain;
  }
  var failures = 0, tickTimer = null, lastMaster = 0;
  function tick(force) {
    clearTimeout(tickTimer);
    if (!S.session || S.syncing || (document.hidden && !force)) { schedule(); return; }
    S.syncing = S.outbox.length > 0; renderStatus();
    return flushOutbox().then(function () { return S.products.size && S.cursor != null ? pollStock() : fetchAllProducts(); })
      .then(function () { if (Date.now() - lastMaster > 30000) { lastMaster = Date.now(); return pollMaster(); } })
      .then(function () { S.online = true; failures = 0; })
      .catch(function (e) {
        if (e && e.auth) { S.online = false; S.syncing = false; toast('Sesi berakhir. Masuk lagi.', true); doLogout(true); return 'stop'; }
        S.online = false; failures++;
      })
      .then(function (stop) { if (stop === 'stop') return; S.syncing = false; refreshAll(); if ($('dList').open && listState) listState.render(); schedule(); });
  }
  function schedule() { clearTimeout(tickTimer); tickTimer = setTimeout(tick, Math.min(4000 * Math.pow(2, Math.min(failures, 4)), 60000)); }

  // ---------- login / logout ----------
  function showLogin(msg) {
    $('app').hidden = true; $('login').hidden = false;
    $('lServer').value = (S.session && S.session.url) || localStorage.getItem('azp.lastUrl') || '';
    $('lDevice').value = localStorage.getItem('azp.lastDevice') || '';
    var e = $('loginError'); e.hidden = !msg; e.textContent = msg || '';
    ($('lServer').value ? ($('lEmail').value ? $('lPass') : $('lEmail')) : $('lServer')).focus();
  }
  function doLogout(force) {
    clearTimeout(tickTimer);
    var go = function () {
      if (S.session) { localStorage.setItem('azp.lastUrl', S.session.url); localStorage.setItem('azp.lastDevice', S.session.device); }
      S.session = null; localStorage.removeItem('azp.session'); showLogin();
    };
    if (force || !S.outbox.length) { go(); return; }
    ask({ title: 'Keluar akun?', msg: 'Masih ada ' + S.outbox.length + ' transaksi belum terkirim. Transaksi tetap tersimpan di perangkat ini dan terkirim saat masuk lagi.', confirm: true, ok: 'Ya, keluar' }).then(function (ok) { if (ok) go(); });
  }
  function startApp() {
    $('login').hidden = true; $('app').hidden = false;
    S.cursor = null; S.since = null; failures = 0;
    Promise.all([idb.all('products'), idb.all('outbox'), idb.get('meta', 'sync'), idb.all('holds')]).then(function (r) {
      r[0].forEach(function (p) { S.products.set(p.id, p); });
      S.outbox = r[1].sort(function (a, b) { return a.at < b.at ? -1 : 1; });
      if (r[2]) { S.since = r[2].since; S.cursor = r[2].cursor; S.cats = new Map(r[2].cats || []); }
      S.holds = r[3].sort(function (a, b) { return a.at < b.at ? -1 : 1; });
      refreshAll();
      api('GET', 'settings').then(function (res) {
        if (res.status === 200) { Object.assign(S.cfg, res.body.data); localStorage.setItem('azp.cfg', JSON.stringify(S.cfg)); refreshAll(); }
      }).catch(function () { /* offline: pakai pengaturan tersimpan */ });
      tick(true); focusScan();
    });
  }
  function openSettings() {
    $('sServer').value = S.session.url; $('sFooter').value = S.prefs.footer; $('sPrinter').value = S.prefs.printer;
    $('sCols').value = String(S.prefs.cols); $('sDrawer').checked = !!S.prefs.drawer; $('sAuto').checked = !!S.prefs.auto; $('sSound').checked = !!S.prefs.sound;
    $('sUser').textContent = S.session.user.name + ' (' + S.session.user.role + ') - ' + S.session.device;
    $('dSettings').showModal();
  }

  // ---------- keyboard ----------
  var fn = {
    F1: function () { $('dHelp').showModal(); }, F2: function () { focusScan(true); }, F3: actOrderDisc, F4: actQty, F5: actLineDisc,
    F6: actHold, F7: actRecall, F8: actLast, F9: openPay, F10: actHistory
  };
  function openDialog() { return document.querySelector('dialog[open]'); }
  function onKey(e) {
    if (!S.session || $('app').hidden) return;
    var dlg = openDialog();

    // Cegah pintasan bawaan browser (refresh, cari, dsb) saat memakai tombol fungsi.
    if (/^F([1-9]|10)$/.test(e.key) || (e.ctrlKey && /^[prfPRF]$/.test(e.key) && !dlg)) e.preventDefault();

    if (dlg) {
      if (dlg.id === 'dPay') {
        var m = { F1: 'cash', F2: 'transfer', F3: 'qris', F4: 'debit' }[e.key];
        if (m) { setMethod(m); return; }
        if (/^F[5-8]$/.test(e.key)) {
          var btns = $('pQuick').querySelectorAll('button'), b = btns[parseInt(e.key.slice(1), 10) - 5];
          if (b) { $('pPaid').value = b.dataset.v; updateChange(); $('pPaid').focus(); }
        }
        return;
      }
      if (dlg.id === 'dReceipt' && (e.key === 'p' || e.key === 'P')) { e.preventDefault(); printReceipt(); return; }
      if (dlg.id === 'dList' && listState) {
        if (e.key === 'ArrowDown') { e.preventDefault(); listState.move(1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); listState.move(-1); }
        else if (e.key === 'Enter') { e.preventDefault(); listState.enter(); }
        else if (e.key === 'Delete') { e.preventDefault(); listState.del(); }
      }
      return;
    }

    if (fn[e.key]) { e.preventDefault(); fn[e.key](); return; }
    var scan = $('scan'), empty = scan.value === '', inScan = document.activeElement === scan;

    if (e.key === 'Delete' && e.ctrlKey) { e.preventDefault(); actClear(); return; }
    if (inScan && !empty && S.sugg.length) {
      if (e.key === 'ArrowDown') { e.preventDefault(); S.suggIdx = Math.min(S.sugg.length - 1, S.suggIdx + 1); renderSugg(); return; }
      if (e.key === 'ArrowUp') { e.preventDefault(); S.suggIdx = Math.max(0, S.suggIdx - 1); renderSugg(); return; }
    }
    if (e.key === 'Escape') { if (!empty) { clearScan(); } return; }
    if (e.key === 'Enter' && inScan) { e.preventDefault(); submitScan(); return; }
    if (empty && S.cart.length && !(document.activeElement && /INPUT|SELECT|TEXTAREA/.test(document.activeElement.tagName) && !inScan)) {
      if (e.key === 'ArrowDown') { e.preventDefault(); S.sel = Math.min(S.cart.length - 1, S.sel + 1); renderCart(); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); S.sel = Math.max(0, S.sel - 1); renderCart(); }
      else if (e.key === 'PageDown') { e.preventDefault(); S.sel = Math.min(S.cart.length - 1, S.sel + 5); renderCart(); }
      else if (e.key === 'PageUp') { e.preventDefault(); S.sel = Math.max(0, S.sel - 5); renderCart(); }
      else if (e.key === 'Home') { e.preventDefault(); S.sel = 0; renderCart(); }
      else if (e.key === 'End') { e.preventDefault(); S.sel = S.cart.length - 1; renderCart(); }
      else if (e.key === 'Delete') { e.preventDefault(); removeLine(S.sel); }
      else if (e.key === '+' || e.key === '=') { e.preventDefault(); var l = selLine(); if (l) addProduct(S.products.get(l.id), 1000); }
      else if (e.key === '-' || e.key === '_') {
        e.preventDefault(); var l2 = selLine();
        if (l2) { l2.milli -= 1000; if (l2.milli <= 0) removeLine(S.sel); else renderCart(S.sel); }
      }
    }
    // ketik huruf/angka di mana saja -> otomatis ke kolom scan (scanner barcode mengetik seperti keyboard)
    if (!inScan && e.key.length === 1 && !e.ctrlKey && !e.altKey && !e.metaKey && !/INPUT|SELECT|TEXTAREA/.test((document.activeElement || {}).tagName || '')) focusScan();
  }

  // ---------- event ----------
  function bind() {
    document.addEventListener('keydown', onKey, true);
    $('loginForm').addEventListener('submit', function (e) {
      e.preventDefault();
      var url = $('lServer').value.trim().replace(/\/+$/, ''), btn = $('lBtn'); btn.disabled = true; btn.textContent = 'Masuk...';
      fetch(url + '/api/v1/auth/login', {
        method: 'POST', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: $('lEmail').value.trim(), password: $('lPass').value, device_name: $('lDevice').value.trim(), device_type: 'desktop' })
      }).then(function (r) { return r.json().then(function (j) { return { s: r.status, j: j }; }); })
        .then(function (r) {
          if (r.s === 201) {
            S.session = { url: url, token: r.j.token, user: r.j.user, device: $('lDevice').value.trim() };
            saveSession(); $('lPass').value = ''; idb.clear('products'); S.products = new Map(); startApp();
          } else {
            var e2 = $('loginError'); e2.hidden = false;
            e2.textContent = (r.j.errors && Object.values(r.j.errors)[0][0]) || r.j.message || 'Gagal masuk.';
          }
        }, function () { var e3 = $('loginError'); e3.hidden = false; e3.textContent = 'Server tidak bisa dihubungi. Periksa alamat dan internet.'; })
        .then(function () { btn.disabled = false; btn.textContent = 'Masuk (Enter)'; });
    });

    $('scan').addEventListener('input', updateSugg);
    $('sugg').addEventListener('mousedown', function (e) { e.preventDefault(); var li = e.target.closest('li[data-i]'); if (!li) return; var sc = parseScan($('scan').value); addProduct(S.sugg[parseInt(li.dataset.i, 10)], sc.milli); clearScan(); focusScan(); });
    $('rows').addEventListener('click', function (e) { var tr = e.target.closest('tr[data-i]'); if (tr) { S.sel = parseInt(tr.dataset.i, 10); renderCart(); focusScan(); } });
    $('btnPay').addEventListener('click', openPay);
    $('btnQueue').addEventListener('click', actQueue);
    $('btnSettings').addEventListener('click', openSettings);
    document.querySelector('.keys').addEventListener('click', function (e) { var b = e.target.closest('[data-key]'); if (b && fn[b.dataset.key]) fn[b.dataset.key](); });

    document.addEventListener('click', function (e) { if (e.target.closest('[data-close]')) { var d = e.target.closest('dialog'); if (d) d.close(); } });
    ['dPay', 'dReceipt', 'dAsk', 'dList', 'dSettings', 'dHelp'].forEach(function (id) {
      $(id).addEventListener('close', function () { if (!openDialog() && !$('app').hidden) setTimeout(function () { focusScan(); }, 0); });
    });
    $('lItems').addEventListener('click', function (e) { var li = e.target.closest('li[data-i]'); if (li && listState) listState.pick(parseInt(li.dataset.i, 10)); });

    $('pPaid').addEventListener('input', updateChange);
    $('pMethods').addEventListener('change', payMethodChanged);
    $('pQuick').addEventListener('click', function (e) { var b = e.target.closest('[data-v]'); if (b) { $('pPaid').value = b.dataset.v; updateChange(); $('pPaid').focus(); } });
    $('payForm').addEventListener('submit', function (e) {
      e.preventDefault(); $('pOk').disabled = true;
      commitSale().then(function () { $('pOk').disabled = false; }, function () { $('pOk').disabled = false; toast('Gagal menyimpan transaksi di perangkat.', true); });
    });
    $('rPrint').addEventListener('click', printReceipt);
    $('setForm').addEventListener('submit', function () {
      S.session.url = $('sServer').value.trim().replace(/\/+$/, ''); saveSession();
      S.prefs.footer = $('sFooter').value; S.prefs.printer = $('sPrinter').value.trim(); S.prefs.cols = parseInt($('sCols').value, 10);
      S.prefs.drawer = $('sDrawer').checked; S.prefs.auto = $('sAuto').checked; S.prefs.sound = $('sSound').checked; savePrefs(); toast('Pengaturan disimpan.');
    });
    $('sLogout').addEventListener('click', function () { $('dSettings').close(); doLogout(false); });

    window.addEventListener('online', function () { tick(true); });
    window.addEventListener('offline', function () { S.online = false; renderStatus(); });
    document.addEventListener('visibilitychange', function () { if (!document.hidden) tick(true); });
    window.addEventListener('focus', function () { if (!$('app').hidden && !openDialog()) focusScan(); });
    setInterval(tickClock, 20000); tickClock();
  }

  // ---------- mulai ----------
  loadLocal(); bind();
  if (inApp) window.chrome.webview.addEventListener('message', function (e) { if (e.data && e.data.type === 'print-result' && !e.data.ok) toast('Gagal cetak: ' + e.data.error, true); });
  if (S.session) startApp(); else showLogin();
})();

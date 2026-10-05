/*
 * Azola Pos (kasir). Satu file, tanpa library.
 * - Data produk disimpan di IndexedDB, jadi kasir tetap jalan saat internet putus.
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
  var uuid = function () { return (crypto.randomUUID ? crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) { var r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 3 | 8)).toString(16); })); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var inApp = !!(window.chrome && window.chrome.webview);

  function toast(msg, bad) {
    var t = $('toast'); t.textContent = msg; t.className = 'toast' + (bad ? ' bad' : ''); t.hidden = false;
    clearTimeout(toast.t); toast.t = setTimeout(function () { t.hidden = true; }, bad ? 5000 : 2500);
  }

  // ---------- IndexedDB ----------
  var dbp = new Promise(function (res, rej) {
    var r = indexedDB.open('azola-pos', 1);
    r.onupgradeneeded = function () {
      var d = r.result;
      d.createObjectStore('products', { keyPath: 'id' });
      d.createObjectStore('outbox', { keyPath: 'uuid' });
      d.createObjectStore('sales', { keyPath: 'uuid' });
      d.createObjectStore('meta');
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
    session: null,            // {url, token, user, device}
    cfg: { name: 'Azola Pos', address: '', phone: '', tax_rate: 0 },
    prefs: { footer: 'Terima kasih', printer: '', cols: 32, drawer: false, auto: false },
    products: new Map(),      // id -> {..., sMilli}
    outbox: [],               // penjualan belum terkirim
    cart: [],                 // [{id, milli}]
    cat: 0, query: '',
    cursor: null, since: null,
    online: false, syncing: false, lastReceipt: null
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
    }, function (e) { clearTimeout(timer); var err = new Error('network'); err.network = true; throw err; });
  }

  // ---------- stok ----------
  function reserved() {
    var m = new Map();
    S.outbox.forEach(function (o) {
      if (o.state === 'failed') return;
      o.payload.items.forEach(function (it) { m.set(it.product_id, (m.get(it.product_id) || 0) + toMilli(it.qty)); });
    });
    return m;
  }
  var reservedMap = new Map();
  function avail(p) { return p.sMilli - (reservedMap.get(p.id) || 0); }
  function stockState(p) {
    var a = avail(p), min = toMilli(p.min_stock) || 5000;
    return a <= 0 ? 'out' : (a <= min ? 'low' : 'ok');
  }

  // ---------- keranjang ----------
  function cartInCart(id) { return S.cart.filter(function (l) { return l.id === id; })[0]; }
  function addToCart(p, milli) {
    var line = cartInCart(p.id), have = line ? line.milli : 0, max = avail(p);
    milli = milli || 1000;
    if (max <= 0) { toast('Stok ' + p.name + ' habis.', true); return; }
    if (have + milli > max) {
      if (have >= max) { toast('Stok ' + p.name + ' hanya ' + pretty(max) + ' ' + p.unit + '.', true); return; }
      milli = max - have;
      toast('Stok ' + p.name + ' hanya ' + pretty(max) + ' ' + p.unit + '.', true);
    }
    if (line) line.milli += milli; else S.cart.push({ id: p.id, milli: milli });
    renderCart();
  }
  function totals() {
    var sub = 0;
    S.cart.forEach(function (l) { var p = S.products.get(l.id); if (p) sub += value(l.milli, p.price); });
    var disc = Math.max(0, Math.min(sub, parseInt($('disc').value || '0', 10) || 0));
    var net = sub - disc, tax = Math.round(net * (S.cfg.tax_rate || 0) / 100);
    return { sub: sub, disc: disc, tax: tax, total: net + tax };
  }

  // ---------- render ----------
  function renderChips() {
    var cats = new Map();
    S.products.forEach(function (p) { if (p.is_active && p.category_name) cats.set(p.category_id, p.category_name); });
    var h = '<button class="chip" type="button" data-cat="0" aria-pressed="' + (S.cat === 0) + '">Semua</button>';
    cats.forEach(function (name, id) { h += '<button class="chip" type="button" data-cat="' + id + '" aria-pressed="' + (S.cat === id) + '">' + esc(name) + '</button>'; });
    $('chips').innerHTML = h;
  }
  function matches(p, q) {
    return (p.name + ' ' + p.sku + ' ' + (p.barcode || '')).toLowerCase().indexOf(q) !== -1;
  }
  function visible() {
    var q = S.query.trim().toLowerCase(), out = [];
    S.products.forEach(function (p) {
      if (!p.is_active) return;
      if (S.cat && p.category_id !== S.cat) return;
      if (q && !matches(p, q)) return;
      out.push(p);
    });
    out.sort(function (a, b) { return a.name.localeCompare(b.name, 'id'); });
    return out;
  }
  function renderGrid() {
    var list = visible().slice(0, 300), h = '';
    list.forEach(function (p) {
      var st = stockState(p), a = avail(p);
      h += '<button class="card" type="button" data-id="' + p.id + '"' + (a <= 0 ? ' disabled' : '') + '>' +
        '<span class="card__name">' + esc(p.name) + '</span><span class="card__sku">' + esc(p.sku) + '</span>' +
        '<span class="card__foot"><span class="tag">' + rp(p.price) + '</span>' +
        '<span class="stock" data-s="' + st + '">' + (a <= 0 ? 'Habis' : pretty(a) + ' ' + esc(p.unit)) + '</span></span></button>';
    });
    $('grid').innerHTML = h;
    $('empty').hidden = list.length > 0;
  }
  function renderCart() {
    var h = '', warn = false;
    S.cart = S.cart.filter(function (l) { return S.products.has(l.id); });
    S.cart.forEach(function (l) {
      var p = S.products.get(l.id), over = l.milli > avail(p);
      if (over) warn = true;
      h += '<li class="line" data-id="' + p.id + '"><span class="line__name">' + esc(p.name) + '</span><span class="line__total">' + rp(value(l.milli, p.price)) + '</span>' +
        '<div class="line__ctrl"><span class="unit">' + rp(p.price) + ' / ' + esc(p.unit) + '</span>' +
        '<button type="button" data-act="dec" aria-label="Kurangi">&minus;</button>' +
        '<input type="text" inputmode="decimal" data-act="qty" value="' + pretty(l.milli) + '" aria-label="Jumlah ' + esc(p.name) + '">' +
        '<button type="button" data-act="inc" aria-label="Tambah">+</button>' +
        '<button type="button" data-act="del" aria-label="Hapus">&times;</button></div>' +
        (over ? '<div class="line__warn">Stok tersisa ' + pretty(avail(p)) + ' ' + esc(p.unit) + '. Kurangi jumlahnya.</div>' : '') + '</li>';
    });
    $('lines').innerHTML = h;
    $('cartEmpty').hidden = S.cart.length > 0;
    var t = totals();
    $('sSub').textContent = rp(t.sub); $('sTax').textContent = rp(t.tax); $('sTotal').textContent = rp(t.total);
    $('taxRow').hidden = !(S.cfg.tax_rate > 0); $('taxPct').textContent = '(' + S.cfg.tax_rate + '%)';
    $('btnPay').disabled = S.cart.length === 0 || warn;
    $('cbCount').textContent = S.cart.length; $('cbTotal').textContent = rp(t.total);
    $('cartBar').hidden = false;
  }
  function renderStatus() {
    var el = $('status');
    el.className = 'status' + (S.syncing ? ' is-sync' : (S.online ? '' : ' is-off'));
    $('statusText').textContent = S.syncing ? 'Menyinkronkan' : (S.online ? 'Tersambung' : 'Offline, transaksi disimpan dulu');
    var n = S.outbox.length; $('queueN').textContent = n;
    $('storeName').textContent = S.cfg.name || 'Azola Pos';
  }
  function refreshAll() { reservedMap = reserved(); renderChips(); renderGrid(); renderCart(); renderStatus(); }

  // ---------- sinkronisasi ----------
  function mapProduct(p, catNames) {
    p.sMilli = toMilli(p.stock);
    p.category_name = catNames.get(p.category_id) || '';
    return p;
  }
  function fetchAllProducts() {
    var cats = new Map(), all = [], page = 1;
    return api('GET', 'categories').then(function (r) {
      if (r.status === 401) throw authError();
      (r.body.data || []).forEach(function (c) { cats.set(c.id, c.name); });
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
      var list = all.map(function (p) { return mapProduct(p, cats); });
      S.products = new Map(list.map(function (p) { return [p.id, p]; }));
      S.cats = cats; S.since = last.server_time; S.cursor = last.stock_cursor;
      return idb.clear('products').then(function () { return idb.putMany('products', list); })
        .then(function () { return idb.put('meta', { since: S.since, cursor: S.cursor, cats: Array.from(cats) }, 'sync'); });
    });
  }
  function authError() { var e = new Error('auth'); e.auth = true; return e; }

  function pollStock() {
    return api('GET', 'sync/stock' + (S.cursor != null ? '?since=' + S.cursor : '')).then(function (r) {
      if (r.status === 401) throw authError();
      if (r.status !== 200) throw new Error('stock ' + r.status);
      var b = r.body;
      if (b.full && S.cursor != null) return fetchAllProducts();
      var changed = [];
      (b.items || []).forEach(function (it) {
        var p = S.products.get(it.id); if (!p) return;
        p.stock = it.stock; p.sMilli = toMilli(it.stock); p.is_active = it.is_active; changed.push(p);
      });
      S.cursor = b.cursor;
      if (changed.length) idb.putMany('products', changed);
    });
  }
  function pollMaster() {
    if (!S.since) return Promise.resolve();
    return api('GET', 'products?per_page=1000&updated_since=' + encodeURIComponent(S.since)).then(function (r) {
      if (r.status !== 200) return;
      var changed = [], ids = S.cats || new Map();
      (r.body.data || []).forEach(function (p) { var q = mapProduct(p, ids); S.products.set(q.id, q); changed.push(q); });
      S.since = r.body.server_time;
      if (changed.length) idb.putMany('products', changed);
    });
  }
  function flushOutbox() {
    var pending = S.outbox.filter(function (o) { return o.state !== 'failed'; });
    var chain = Promise.resolve();
    pending.forEach(function (o) {
      chain = chain.then(function () {
        return api('POST', 'orders', o.payload).then(function (r) {
          if (r.status === 200 || r.status === 201) {
            var d = r.body.data || {};
            S.outbox = S.outbox.filter(function (x) { return x.uuid !== o.uuid; });
            return idb.del('outbox', o.uuid).then(function () {
              return idb.get('sales', o.uuid).then(function (sale) {
                if (sale) { sale.number = d.number; sale.synced = true; return idb.put('sales', sale); }
              });
            });
          }
          if (r.status === 401) throw authError();
          if (r.status === 422) {
            o.state = 'failed'; o.error = (r.body && r.body.message) || 'Ditolak server';
            return idb.put('outbox', o);
          }
          throw new Error('order ' + r.status); // 5xx: coba lagi nanti
        });
      });
    });
    return chain;
  }

  var failures = 0, tickTimer = null, lastMaster = 0;
  function tick(force) {
    clearTimeout(tickTimer);
    if (!S.session || S.syncing) { schedule(); return; }
    if (document.hidden && !force) { schedule(); return; }
    S.syncing = S.outbox.length > 0; renderStatus();
    var job = flushOutbox().then(function () { return S.products.size && S.cursor != null ? pollStock() : fetchAllProducts(); })
      .then(function () {
        if (Date.now() - lastMaster > 30000) { lastMaster = Date.now(); return pollMaster(); }
      })
      .then(function () { S.online = true; failures = 0; })
      .catch(function (e) {
        if (e && e.auth) { S.online = false; toast('Sesi berakhir. Masuk lagi.', true); doLogout(true); return; }
        S.online = false; failures++;
      })
      .then(function () { S.syncing = false; refreshAll(); if (!$('dQueue').hidden && $('dQueue').open) renderQueue(); schedule(); });
    return job;
  }
  function schedule() { clearTimeout(tickTimer); tickTimer = setTimeout(tick, Math.min(4000 * Math.pow(2, Math.min(failures, 4)), 60000)); }

  // ---------- transaksi ----------
  var payMethods = [['cash', 'Tunai'], ['transfer', 'Transfer'], ['qris', 'QRIS'], ['debit', 'Debit']];
  function openPay() {
    if (!S.cart.length) return;
    var t = totals();
    $('pTotal').textContent = rp(t.total);
    $('pMethods').innerHTML = payMethods.map(function (m, i) {
      return '<label><input type="radio" name="pm" value="' + m[0] + '"' + (i === 0 ? ' checked' : '') + '>' + m[1] + '</label>';
    }).join('');
    $('pPaid').value = t.total; $('pName').value = '';
    updateChange();
    var q = [t.total, 20000, 50000, 100000].filter(function (v, i, a) { return v >= t.total && a.indexOf(v) === i; });
    $('pQuick').innerHTML = q.map(function (v) { return '<button type="button" data-v="' + v + '">' + (v === t.total ? 'Uang pas' : rp(v)) + '</button>'; }).join('');
    $('dPay').showModal(); $('pPaid').select();
  }
  function payMethod() { var r = document.querySelector('input[name="pm"]:checked'); return r ? r.value : 'cash'; }
  function updateChange() {
    var t = totals(), paid = parseInt($('pPaid').value || '0', 10) || 0, el = $('pChange');
    if (payMethod() !== 'cash') { el.className = 'dlg__line'; el.textContent = paid < t.total ? 'Kurang ' + rp(t.total - paid) + ' (dicatat sebagai piutang)' : ''; if (paid < t.total) el.className = 'dlg__line bad'; return; }
    if (paid >= t.total) { el.className = 'dlg__line ok'; el.textContent = 'Kembalian ' + rp(paid - t.total); }
    else { el.className = 'dlg__line bad'; el.textContent = 'Kurang ' + rp(t.total - paid) + ' (dicatat sebagai piutang)'; }
  }
  function commitSale() {
    var t = totals(), paid = parseInt($('pPaid').value || '0', 10) || 0, method = payMethod();
    var items = S.cart.map(function (l) { return { product_id: l.id, qty: fromMilli(l.milli) }; });
    var id = uuid(), now = new Date().toISOString();
    var payload = { uuid: id, items: items, order_discount: t.disc, payment_method: method, paid_total: Math.min(paid, t.total), ordered_at: now };
    var name = $('pName').value.trim(); if (name) payload.customer_name = name;

    var sale = {
      uuid: id, at: now, number: null, synced: false, method: method, cashier: S.session.user.name,
      customer: name, sub: t.sub, disc: t.disc, tax: t.tax, total: t.total, paid: paid, change: Math.max(0, paid - t.total),
      lines: S.cart.map(function (l) { var p = S.products.get(l.id); return { name: p.name, unit: p.unit, milli: l.milli, price: p.price, total: value(l.milli, p.price) }; })
    };
    var entry = { uuid: id, payload: payload, state: 'pending', error: null, at: now };
    S.outbox.push(entry);
    return Promise.all([idb.put('outbox', entry), idb.put('sales', sale)]).then(function () {
      S.cart = []; $('disc').value = 0; $('dPay').close(); closeCart();
      refreshAll(); showReceipt(sale, true);
      tick(true);
    });
  }

  // ---------- struk ----------
  function receiptLines(sale, cols) {
    var L = [], line = function (a, b) { var gap = cols - a.length - b.length; return gap > 0 ? a + ' '.repeat(gap) + b : a + ' ' + b; };
    var center = function (s) { s = s.slice(0, cols); return ' '.repeat(Math.max(0, Math.floor((cols - s.length) / 2))) + s; };
    var rule = '-'.repeat(cols), d = new Date(sale.at);
    var pad2 = function (n) { return String(n).padStart(2, '0'); };
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
      L.push(line('  ' + pretty(l.milli) + ' ' + l.unit + ' x ' + l.price.toLocaleString('id-ID'), l.total.toLocaleString('id-ID')));
    });
    L.push(rule);
    L.push(line('Subtotal', rp(sale.sub)));
    if (sale.disc) L.push(line('Diskon', '-' + rp(sale.disc)));
    if (sale.tax) L.push(line('Pajak', rp(sale.tax)));
    L.push(line('TOTAL', rp(sale.total)));
    L.push(line('Bayar (' + sale.method + ')', rp(sale.paid)));
    if (sale.paid >= sale.total) { if (sale.change) L.push(line('Kembali', rp(sale.change))); }
    else L.push(line('Kurang', rp(sale.total - sale.paid)));
    L.push(rule);
    if (S.prefs.footer) wrap(S.prefs.footer, cols).forEach(function (s) { L.push(center(s)); });
    return L;
  }
  function wrap(text, cols) {
    var out = [], cur = '';
    String(text).split(/\s+/).forEach(function (w) {
      while (w.length > cols) { if (cur) { out.push(cur); cur = ''; } out.push(w.slice(0, cols)); w = w.slice(cols); }
      if (!cur) cur = w; else if ((cur + ' ' + w).length <= cols) cur += ' ' + w; else { out.push(cur); cur = w; }
    });
    if (cur) out.push(cur);
    return out.length ? out : [''];
  }
  function showReceipt(sale, fresh) {
    S.lastReceipt = sale;
    $('rTitle').textContent = fresh ? 'Transaksi tersimpan' : 'Struk';
    $('rText').textContent = receiptLines(sale, S.prefs.cols).join('\n');
    if (!$('dReceipt').open) $('dReceipt').showModal();
    if (fresh && S.prefs.auto) printReceipt();
  }
  function printReceipt() {
    var sale = S.lastReceipt; if (!sale) return;
    if (inApp) {
      window.chrome.webview.postMessage({ type: 'print', printer: S.prefs.printer, cols: S.prefs.cols, drawer: !!S.prefs.drawer, lines: receiptLines(sale, S.prefs.cols) });
    } else { window.print(); }
  }

  // ---------- dialog antrean / riwayat / pengaturan ----------
  function renderQueue() {
    $('qList').innerHTML = S.outbox.length ? S.outbox.map(function (o) {
      var tot = o.payload.items.length + ' barang';
      return '<li><span>' + new Date(o.at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) + ' &middot; ' + tot +
        (o.state === 'failed' ? '<small class="bad">Ditolak: ' + esc(o.error) + '</small>' : '<small>Menunggu koneksi</small>') + '</span>' +
        (o.state === 'failed' ? '<span><button class="btn btn--ghost btn--sm" data-retry="' + o.uuid + '">Coba lagi</button> <button class="link" data-drop="' + o.uuid + '">Hapus</button></span>' : '') + '</li>';
    }).join('') : '<li class="muted">Tidak ada antrean. Semua transaksi sudah terkirim.</li>';
  }
  function renderHistory() {
    idb.all('sales').then(function (rows) {
      rows.sort(function (a, b) { return a.at < b.at ? 1 : -1; });
      $('hList').innerHTML = rows.slice(0, 40).map(function (s) {
        return '<li><span>' + new Date(s.at).toLocaleString('id-ID', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: 'short' }) + ' &middot; ' + rp(s.total) +
          '<small>' + (s.number || 'belum terkirim') + '</small></span><button class="btn btn--ghost btn--sm" data-rep="' + s.uuid + '">Struk</button></li>';
      }).join('') || '<li class="muted">Belum ada transaksi di perangkat ini.</li>';
      // simpan maksimal 200 riwayat
      if (rows.length > 200) rows.slice(200).forEach(function (s) { if (s.synced) idb.del('sales', s.uuid); });
    });
  }
  function openSettings() {
    $('sServer').value = S.session.url; $('sFooter').value = S.prefs.footer; $('sPrinter').value = S.prefs.printer;
    $('sCols').value = String(S.prefs.cols); $('sDrawer').checked = !!S.prefs.drawer; $('sAuto').checked = !!S.prefs.auto;
    $('sUser').textContent = S.session.user.name + ' (' + S.session.user.role + ') - ' + S.session.device;
    $('dSettings').showModal();
  }

  // ---------- login / logout ----------
  function showLogin(msg) {
    $('app').hidden = true; $('login').hidden = false;
    $('lServer').value = (S.session && S.session.url) || localStorage.getItem('azp.lastUrl') || '';
    $('lDevice').value = localStorage.getItem('azp.lastDevice') || '';
    var e = $('loginError'); e.hidden = !msg; e.textContent = msg || '';
  }
  function doLogout(keepLocal) {
    clearTimeout(tickTimer);
    if (S.session) { localStorage.setItem('azp.lastUrl', S.session.url); localStorage.setItem('azp.lastDevice', S.session.device); }
    var go = function () { S.session = null; localStorage.removeItem('azp.session'); showLogin(); };
    if (keepLocal) { go(); return; }
    // keluar normal: hanya boleh kalau antrean kosong, supaya tidak ada transaksi yang hilang
    if (S.outbox.length && !confirm('Masih ada ' + S.outbox.length + ' transaksi belum terkirim. Keluar sekarang? Transaksi tetap tersimpan di perangkat ini.')) return;
    go();
  }
  function startApp() {
    $('login').hidden = true; $('app').hidden = false;
    S.cursor = null; S.since = null; failures = 0;
    Promise.all([idb.all('products'), idb.all('outbox'), idb.get('meta', 'sync')]).then(function (r) {
      r[0].forEach(function (p) { S.products.set(p.id, p); });
      S.outbox = r[1].sort(function (a, b) { return a.at < b.at ? -1 : 1; });
      if (r[2]) { S.since = r[2].since; S.cursor = r[2].cursor; S.cats = new Map(r[2].cats || []); }
      refreshAll();
      api('GET', 'settings').then(function (res) {
        if (res.status === 200) { Object.assign(S.cfg, res.body.data); localStorage.setItem('azp.cfg', JSON.stringify(S.cfg)); refreshAll(); }
      }).catch(function () { /* offline: pakai pengaturan tersimpan */ });
      tick(true);
      $('q').focus();
    });
  }

  // ---------- event ----------
  function closeCart() { $('cart').classList.remove('is-open'); }
  function bind() {
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
            saveSession(); $('lPass').value = '';
            idb.clear('products'); S.products = new Map();
            startApp();
          } else {
            var e2 = $('loginError'); e2.hidden = false;
            e2.textContent = (r.j.errors && Object.values(r.j.errors)[0][0]) || r.j.message || 'Gagal masuk.';
          }
        }, function () { var e3 = $('loginError'); e3.hidden = false; e3.textContent = 'Server tidak bisa dihubungi. Periksa alamat dan internet.'; })
        .then(function () { btn.disabled = false; btn.textContent = 'Masuk'; });
    });

    $('q').addEventListener('input', function () { S.query = this.value; renderGrid(); });
    $('q').addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      var q = this.value.trim().toLowerCase(); if (!q) return;
      var exact = null;
      S.products.forEach(function (p) { if (p.is_active && ((p.barcode && p.barcode.toLowerCase() === q) || p.sku.toLowerCase() === q)) exact = p; });
      var only = visible(); var pick = exact || (only.length === 1 ? only[0] : null);
      if (pick) { addToCart(pick); this.value = ''; S.query = ''; renderGrid(); }
      else if (!only.length) toast('Produk "' + this.value + '" tidak ditemukan.', true);
    });
    $('chips').addEventListener('click', function (e) { var b = e.target.closest('[data-cat]'); if (!b) return; S.cat = parseInt(b.dataset.cat, 10); renderChips(); renderGrid(); });
    $('grid').addEventListener('click', function (e) { var b = e.target.closest('[data-id]'); if (b && !b.disabled) addToCart(S.products.get(parseInt(b.dataset.id, 10))); });

    $('lines').addEventListener('click', function (e) {
      var li = e.target.closest('li'), b = e.target.closest('[data-act]'); if (!li || !b) return;
      var id = parseInt(li.dataset.id, 10), l = cartInCart(id), p = S.products.get(id);
      if (b.dataset.act === 'inc') addToCart(p, 1000);
      if (b.dataset.act === 'dec') { l.milli -= 1000; if (l.milli <= 0) S.cart = S.cart.filter(function (x) { return x.id !== id; }); renderCart(); }
      if (b.dataset.act === 'del') { S.cart = S.cart.filter(function (x) { return x.id !== id; }); renderCart(); }
    });
    $('lines').addEventListener('change', function (e) {
      if (e.target.dataset.act !== 'qty') return;
      var li = e.target.closest('li'), id = parseInt(li.dataset.id, 10), l = cartInCart(id), m = toMilli(e.target.value);
      if (m <= 0) S.cart = S.cart.filter(function (x) { return x.id !== id; }); else l.milli = m;
      renderCart();
    });
    $('disc').addEventListener('input', renderCart);
    $('btnClear').addEventListener('click', function () { if (S.cart.length && confirm('Kosongkan keranjang?')) { S.cart = []; $('disc').value = 0; renderCart(); } });
    $('btnPay').addEventListener('click', openPay);
    $('cartBar').addEventListener('click', function () { $('cart').classList.add('is-open'); });
    $('btnCartClose').addEventListener('click', closeCart);

    $('pPaid').addEventListener('input', updateChange);
    $('pMethods').addEventListener('change', function () {
      var t = totals(); if (payMethod() !== 'cash') $('pPaid').value = t.total; updateChange();
    });
    $('pQuick').addEventListener('click', function (e) { var b = e.target.closest('[data-v]'); if (b) { $('pPaid').value = b.dataset.v; updateChange(); } });
    $('payForm').addEventListener('submit', function (e) { e.preventDefault(); $('pOk').disabled = true; commitSale().then(function () { $('pOk').disabled = false; }, function () { $('pOk').disabled = false; toast('Gagal menyimpan transaksi di perangkat.', true); }); });

    document.addEventListener('click', function (e) { if (e.target.closest('[data-close]')) { var d = e.target.closest('dialog'); if (d) d.close(); } });
    $('rPrint').addEventListener('click', printReceipt);

    $('btnQueue').addEventListener('click', function () { renderQueue(); $('dQueue').showModal(); });
    $('qRetry').addEventListener('click', function () { S.outbox.forEach(function (o) { if (o.state === 'failed') { o.state = 'pending'; idb.put('outbox', o); } }); reservedMap = reserved(); tick(true); toast('Mengirim...'); });
    $('qList').addEventListener('click', function (e) {
      var r = e.target.closest('[data-retry]'), d = e.target.closest('[data-drop]');
      if (r) { var o = S.outbox.filter(function (x) { return x.uuid === r.dataset.retry; })[0]; o.state = 'pending'; idb.put('outbox', o); refreshAll(); renderQueue(); tick(true); }
      if (d && confirm('Hapus transaksi ini dari antrean? Stok di server tidak berubah dan struknya tidak akan tercatat di pembukuan.')) {
        S.outbox = S.outbox.filter(function (x) { return x.uuid !== d.dataset.drop; }); idb.del('outbox', d.dataset.drop); refreshAll(); renderQueue();
      }
    });
    $('btnHistory').addEventListener('click', function () { renderHistory(); $('dHistory').showModal(); });
    $('hList').addEventListener('click', function (e) {
      var b = e.target.closest('[data-rep]'); if (!b) return;
      idb.get('sales', b.dataset.rep).then(function (s) { if (s) { $('dHistory').close(); showReceipt(s, false); } });
    });
    $('btnSettings').addEventListener('click', openSettings);
    $('setForm').addEventListener('submit', function () {
      S.session.url = $('sServer').value.trim().replace(/\/+$/, ''); saveSession();
      S.prefs.footer = $('sFooter').value; S.prefs.printer = $('sPrinter').value.trim(); S.prefs.cols = parseInt($('sCols').value, 10);
      S.prefs.drawer = $('sDrawer').checked; S.prefs.auto = $('sAuto').checked; savePrefs(); toast('Pengaturan disimpan.');
    });
    $('sLogout').addEventListener('click', function () { $('dSettings').close(); doLogout(false); });

    document.addEventListener('keydown', function (e) {
      if (!S.session || $('app').hidden) return;
      if (e.key === 'F2') { e.preventDefault(); $('q').focus(); $('q').select(); }
      if (e.key === 'F9') { e.preventDefault(); if (!document.querySelector('dialog[open]')) openPay(); }
    });
    window.addEventListener('online', function () { tick(true); });
    window.addEventListener('offline', function () { S.online = false; renderStatus(); });
    document.addEventListener('visibilitychange', function () { if (!document.hidden) tick(true); });
  }

  // ---------- mulai ----------
  loadLocal(); bind();
  if (window.chrome && window.chrome.webview) {
    window.chrome.webview.addEventListener('message', function (e) { if (e.data && e.data.type === 'print-result' && !e.data.ok) toast('Gagal cetak: ' + e.data.error, true); });
  }
  if (S.session) startApp(); else showLogin();
})();

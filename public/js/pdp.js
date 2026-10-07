/*
 * Halaman detail produk: galeri foto (geser, miniatur, perbesar) dan pemilih variasi.
 * Tanpa library. Tanpa JS, halaman tetap lengkap: tabel variasi berisi semua SKU dan harga,
 * dan tombol beli memakai variasi pertama yang tersedia.
 */
(function () {
  'use strict';
  var root = document.querySelector('[data-pdp]');
  if (!root) return;

  /* ---------------- galeri ---------------- */
  var track = root.querySelector('[data-track]');
  var slides = track ? [].slice.call(track.children) : [];
  var thumbs = [].slice.call(root.querySelectorAll('[data-thumb]'));
  var count = root.querySelector('[data-count]');
  var index = 0;

  function show(i, smooth) {
    if (!track || !slides.length) return;
    index = Math.max(0, Math.min(slides.length - 1, i));
    track.scrollTo({ left: slides[index].offsetLeft - track.offsetLeft, behavior: smooth === false ? 'auto' : 'smooth' });
    mark();
  }
  function mark() {
    thumbs.forEach(function (t, n) { if (n === index) t.setAttribute('aria-current', 'true'); else t.removeAttribute('aria-current'); });
    if (count) count.textContent = (index + 1) + ' / ' + slides.length;
    var cur = thumbs[index];
    if (cur && cur.parentNode && cur.parentNode.parentNode && cur.parentNode.parentNode.scrollTo) {
      var list = cur.parentNode.parentNode;
      if (list.scrollWidth > list.clientWidth) list.scrollTo({ left: cur.parentNode.offsetLeft - 8, behavior: 'smooth' });
    }
  }

  if (track) {
    var settle = 0;
    track.addEventListener('scroll', function () {
      clearTimeout(settle);
      settle = setTimeout(function () {
        var w = slides[0].offsetWidth || 1;
        var i = Math.round(track.scrollLeft / w);
        if (i !== index) { index = i; mark(); }
      }, 60);
    }, { passive: true });
    track.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowRight') { e.preventDefault(); show(index + 1); }
      if (e.key === 'ArrowLeft') { e.preventDefault(); show(index - 1); }
    });
  }
  thumbs.forEach(function (t, n) { t.addEventListener('click', function () { show(n); }); });
  var prev = root.querySelector('[data-prev]'), next = root.querySelector('[data-next]');
  if (prev) prev.addEventListener('click', function () { show(index - 1); });
  if (next) next.addEventListener('click', function () { show(index + 1); });

  /* ---------------- lightbox ---------------- */
  var box = document.querySelector('[data-lightbox]');
  if (box && typeof box.showModal === 'function') {
    var big = box.querySelector('img');
    var open = function (i) {
      var src = slides[i] && slides[i].querySelector('img');
      if (!src) return;
      big.src = src.currentSrc || src.src; big.alt = src.alt;
      box.showModal();
    };
    [].forEach.call(root.querySelectorAll('[data-zoom]'), function (b) {
      b.addEventListener('click', function () { open(parseInt(b.dataset.zoom, 10)); });
    });
    box.addEventListener('click', function (e) { if (e.target === box || e.target.hasAttribute('data-close')) box.close(); });
  }

  /* ---------------- pemilih variasi ---------------- */
  var dataEl = document.getElementById('pdp-data');
  if (!dataEl) return;
  var data;
  try { data = JSON.parse(dataEl.textContent); } catch (e) { return; }
  var variants = data.variants || [];
  var axes = data.axes || [];
  var form = root.querySelector('[data-form]');
  var chosen = {};
  var current = variants.filter(function (v) { return v.id === data.selected; })[0] || variants[0];
  if (!current) return;

  function rowState(v) {
    var row = document.querySelector('[data-row="' + v.id + '"] [data-stock-id]');
    var el = row || (document.querySelector('[data-main-stock]').dataset.stockId == v.id ? document.querySelector('[data-main-stock]') : null);
    if (el) return { state: el.dataset.state, label: el.querySelector('[data-stock-text]').textContent };
    return null;
  }
  function available(v) {
    var s = rowState(v);
    return s ? s.state !== 'out' : true;
  }
  function matches(v, picks, skip) {
    return axes.every(function (a) { return a.name === skip || picks[a.name] === undefined || v.options[a.name] === picks[a.name]; });
  }

  function paint() {
    // Tombol pilihan: coret kalau semua kombinasinya habis, pudar kalau kombinasinya tidak ada.
    [].forEach.call(root.querySelectorAll('[data-axis]'), function (fs) {
      var name = fs.dataset.axis;
      var label = fs.querySelector('[data-axis-value]');
      if (label) label.textContent = chosen[name] || '';
      [].forEach.call(fs.querySelectorAll('[data-value]'), function (btn) {
        var value = btn.dataset.value;
        var pool = variants.filter(function (v) { return v.options[name] === value && matches(v, chosen, name); });
        btn.setAttribute('aria-pressed', chosen[name] === value ? 'true' : 'false');
        btn.classList.toggle('is-off', pool.length === 0);
        btn.classList.toggle('is-out', pool.length > 0 && !pool.some(available));
      });
    });
  }

  function apply(v, fromImage) {
    current = v;
    axes.forEach(function (a) { chosen[a.name] = v.options[a.name]; });

    document.querySelectorAll('[data-price]').forEach(function (el) { el.textContent = v.priceLabel; });
    document.querySelectorAll('[data-unit]').forEach(function (el) { el.textContent = v.unit; });
    document.querySelectorAll('[data-sku]').forEach(function (el) { el.textContent = v.sku; });
    var bc = document.querySelector('[data-barcode]'), bcRow = document.querySelector('[data-barcode-row]');
    if (bc && bcRow) { bc.textContent = v.barcode || ''; bcRow.hidden = !v.barcode; }
    var nm = document.querySelector('[data-buybar-name]');
    if (nm) nm.textContent = v.variant || nm.textContent;

    var main = document.querySelector('[data-main-stock]');
    var st = rowState(v);
    main.dataset.stockId = v.id;
    if (st) { main.dataset.state = st.state; main.querySelector('[data-stock-text]').textContent = st.label; }

    document.querySelectorAll('[data-buy-btn]').forEach(function (b) {
      b.dataset.buy = v.id;
      delete b.dataset.label; // biar live-stock mengambil label baru
      var ok = st ? st.state !== 'out' : true;
      b.disabled = !ok;
      var isNow = b.name === 'langsung';
      b.textContent = ok ? (isNow ? 'Beli sekarang' : 'Tambah ke keranjang') : 'Stok habis';
    });
    if (form) form.action = v.cart;

    document.querySelectorAll('[data-row]').forEach(function (r) { r.classList.toggle('is-on', r.dataset.row === String(v.id)); });
    if (!fromImage && v.image !== null && v.image !== undefined) show(v.image);
    paint();
  }

  root.querySelectorAll('[data-axis] [data-value]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var name = btn.closest('[data-axis]').dataset.axis;
      var value = btn.dataset.value;
      var picks = {}; Object.keys(chosen).forEach(function (k) { picks[k] = chosen[k]; });
      picks[name] = value;
      // Cari kombinasi yang persis; kalau tidak ada, ambil yang paling mirip dengan pilihan lain.
      var pool = variants.filter(function (v) { return v.options[name] === value; });
      var exact = pool.filter(function (v) { return matches(v, picks); });
      var best = (exact.length ? exact : pool).slice().sort(function (a, b) {
        var score = function (v) { return axes.reduce(function (n, ax) { return n + (v.options[ax.name] === picks[ax.name] ? 1 : 0); }, 0) * 10 + (available(v) ? 1 : 0); };
        return score(b) - score(a);
      })[0];
      if (best) apply(best);
    });
  });
  document.querySelectorAll('[data-pick]').forEach(function (b) {
    b.addEventListener('click', function () {
      var v = variants.filter(function (x) { return String(x.id) === b.dataset.pick; })[0];
      if (v) { apply(v); root.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });
  });

  // Label stok yang berubah realtime ikut menyalakan/mematikan tombol dan pilihan.
  var obs = new MutationObserver(function () { paint(); });
  document.querySelectorAll('.vtable [data-stock-id]').forEach(function (el) { obs.observe(el, { attributes: true, attributeFilter: ['data-state'] }); });

  apply(current, true);
  if (current.image) show(current.image, false);
})();

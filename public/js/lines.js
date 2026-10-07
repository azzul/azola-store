/*
 * Editor baris barang untuk form admin (pembelian, retur, alih gudang, penyesuaian, penjualan).
 * Ketik nama/SKU/barcode lalu Enter; barcode persis langsung masuk. Satuan besar mengalikan jumlah dasar.
 */
(function () {
  'use strict';

  var fmt = new Intl.NumberFormat('id-ID');
  var rp = function (n) { return 'Rp' + fmt.format(Math.round(n || 0)); };
  var num = function (v) { var n = parseFloat(String(v).replace(',', '.')); return isFinite(n) ? n : 0; };

  document.querySelectorAll('[data-lines]').forEach(function (root) {
    var name = root.dataset.name || 'items';
    var mode = root.dataset.mode || 'price';       // price | qty | delta
    var priceFrom = root.dataset.priceFrom || 'cost'; // cost | price
    var lookup = root.dataset.lookup;
    var body = root.querySelector('.lines__body');
    var search = root.querySelector('.lines__search');
    var results = root.querySelector('.lines__results');
    var emptyRow = root.querySelector('.lines__empty');
    var form = root.closest('form');
    var counter = 0, timer = null, active = -1, current = [];

    function unitPrice(p, unit) {
      var u = p.units.find(function (x) { return x.unit === unit; }) || p.units[0];
      return priceFrom === 'cost' ? Math.round(p.cost * u.factor) : u.price;
    }

    function addRow(p, data) {
      data = data || {};
      var existing = !data.fromInitial && Array.prototype.find.call(body.children, function (tr) { return tr.dataset.id === String(p.id); });
      if (existing) {
        var q = existing.querySelector('.js-qty');
        q.value = num(q.value) + 1;
        recalc(); q.focus(); q.select();
        return;
      }

      var i = counter++;
      var unit = data.unit || p.unit;
      var price = data.price !== undefined && data.price !== '' ? data.price : unitPrice(p, unit);
      var tr = document.createElement('tr');
      tr.dataset.id = p.id;
      var units = p.units.length > 1
        ? '<select class="js-unit" name="' + name + '[' + i + '][unit]" aria-label="Satuan">' + p.units.map(function (u) {
            return '<option value="' + esc(u.unit) + '" data-factor="' + u.factor + '"' + (u.unit === unit ? ' selected' : '') + '>' + esc(u.unit) + (u.factor !== 1 ? ' (' + u.factor + ')' : '') + '</option>';
          }).join('') + '</select>'
        : '<input type="hidden" class="js-unit" name="' + name + '[' + i + '][unit]" value="' + esc(p.unit) + '" data-factor="1">' + esc(p.unit);

      tr.innerHTML =
        '<td><input type="hidden" name="' + name + '[' + i + '][product_id]" value="' + p.id + '"><strong>' + esc(p.name) + '</strong><div class="muted">' + esc(p.sku) + ' · stok ' + esc(p.stock) + ' ' + esc(p.unit) + '</div></td>' +
        '<td>' + units + '</td>' +
        '<td><input class="js-qty" type="number" step="any" ' + (mode === 'delta' ? '' : 'min="0" ') + 'name="' + name + '[' + i + '][' + (mode === 'delta' ? 'qty_change' : 'qty') + ']" value="' + (data.qty !== undefined && data.qty !== '' ? data.qty : (mode === 'delta' ? '' : 1)) + '" aria-label="Jumlah" required></td>' +
        (mode === 'price' ? '<td><input class="js-price" type="number" min="0" step="1" name="' + name + '[' + i + '][price]" value="' + price + '" aria-label="Harga"></td><td class="num js-sub">0</td>' : '') +
        '<td><button type="button" class="btn btn--ghost btn--sm js-del" aria-label="Hapus baris">×</button></td>';
      tr._p = p;
      tr._factor = (p.units.find(function (u) { return u.unit === unit; }) || { factor: 1 }).factor;
      body.appendChild(tr);
      recalc();
      if (!data.fromInitial) { var q2 = tr.querySelector('.js-qty'); q2.focus(); q2.select(); }
    }

    function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

    function recalc() {
      var total = 0;
      Array.prototype.forEach.call(body.children, function (tr) {
        var sub = num(tr.querySelector('.js-qty').value) * num((tr.querySelector('.js-price') || { value: 0 }).value);
        var cell = tr.querySelector('.js-sub');
        if (cell) cell.textContent = rp(sub);
        total += sub;
      });
      if (emptyRow) emptyRow.hidden = body.children.length > 0;
      var scope = form || document;
      scope.querySelectorAll('[data-lines-subtotal="' + name + '"]').forEach(function (el) { el.textContent = rp(total); });
      var disc = scope.querySelector('[data-lines-discount="' + name + '"]');
      var grand = total - (disc ? Math.min(num(disc.value), total) : 0);
      scope.querySelectorAll('[data-lines-grand="' + name + '"]').forEach(function (el) { el.textContent = rp(grand); });
      root.dispatchEvent(new CustomEvent('lines:change', { bubbles: true, detail: { total: total, grand: grand, count: body.children.length } }));
    }

    body.addEventListener('input', recalc);
    body.addEventListener('change', function (e) {
      if (!e.target.classList.contains('js-unit')) return;
      var tr = e.target.closest('tr');
      var sel = e.target.selectedOptions ? e.target.selectedOptions[0] : e.target;
      var factor = num(sel.dataset.factor) || 1;
      var priceInput = tr.querySelector('.js-price');
      if (priceInput && tr._factor) priceInput.value = Math.round(num(priceInput.value) * factor / tr._factor);
      tr._factor = factor;
      recalc();
    });
    body.addEventListener('click', function (e) {
      if (e.target.closest('.js-del')) { e.target.closest('tr').remove(); recalc(); }
    });
    if (form) {
      var disc = form.querySelector('[data-lines-discount="' + name + '"]');
      if (disc) disc.addEventListener('input', recalc);
      form.addEventListener('submit', function (e) {
        if (!body.children.length) { e.preventDefault(); search.focus(); search.setCustomValidity('Tambahkan minimal satu barang.'); search.reportValidity(); setTimeout(function () { search.setCustomValidity(''); }, 1500); }
      });
    }

    function close() { results.hidden = true; results.innerHTML = ''; active = -1; current = []; search.setAttribute('aria-expanded', 'false'); }
    function show(list) {
      current = list; active = list.length ? 0 : -1;
      results.innerHTML = list.length ? list.map(function (p, i) {
        return '<li role="option" data-i="' + i + '" class="' + (i === 0 ? 'is-on' : '') + '"><strong>' + esc(p.name) + '</strong><span class="muted"> ' + esc(p.sku) + ' · stok ' + esc(p.stock) + ' ' + esc(p.unit) + '</span></li>';
      }).join('') : '<li class="muted">Tidak ada barang yang cocok.</li>';
      results.hidden = false; search.setAttribute('aria-expanded', 'true');
    }
    function pick(i) { if (current[i]) { addRow(current[i]); search.value = ''; close(); search.focus(); } }

    search.addEventListener('input', function () {
      clearTimeout(timer);
      var q = search.value.trim();
      if (!q) { close(); return; }
      timer = setTimeout(function () {
        var level = form && form.querySelector('[data-price-level]');
        fetch(lookup + '?q=' + encodeURIComponent(q) + (level && level.value ? '&level=' + level.value : ''), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
          .then(function (r) { return r.json(); }).then(function (list) {
            if (search.value.trim() !== q) return;
            show(list);
          }).catch(close);
      }, 160);
    });
    search.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); if (!results.hidden) pick(Math.max(active, 0)); return; }
      if (e.key === 'Escape') { close(); return; }
      if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && current.length) {
        e.preventDefault();
        active = (active + (e.key === 'ArrowDown' ? 1 : -1) + current.length) % current.length;
        results.querySelectorAll('li').forEach(function (li, i) { li.classList.toggle('is-on', i === active); });
      }
    });
    results.addEventListener('mousedown', function (e) { var li = e.target.closest('li[data-i]'); if (li) { e.preventDefault(); pick(+li.dataset.i); } });
    document.addEventListener('click', function (e) { if (!root.contains(e.target)) close(); });

    var initial = [];
    try { initial = JSON.parse(root.dataset.initial || '[]'); } catch (e) {}
    initial.forEach(function (row) { addRow(row.product, { unit: row.unit, qty: row.qty, price: row.price, fromInitial: true }); });
    recalc();
  });
})();

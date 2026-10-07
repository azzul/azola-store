/* Rangka toko: menutup menu/dropdown saat klik di luar atau menekan Esc. */
(function () {
  'use strict';
  var drops = [].slice.call(document.querySelectorAll('details[data-dropdown]'));
  if (!drops.length) return;

  document.addEventListener('click', function (e) {
    drops.forEach(function (d) { if (d.open && !d.contains(e.target)) d.open = false; });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    drops.forEach(function (d) { if (d.open) { d.open = false; var s = d.querySelector('summary'); if (s) s.focus(); } });
  });
  // Buka satu dropdown, tutup yang lain.
  drops.forEach(function (d) {
    d.addEventListener('toggle', function () {
      if (d.open) drops.forEach(function (o) { if (o !== d) o.open = false; });
    });
  });
})();

/*
 * Gerak 3D beranda. Tanpa library, satu pembaruan per frame.
 * Skrip hanya mengisi variabel CSS (--p, --q, --s, --mx, --my); semua gambar dibuat CSS.
 * Kalau skrip gagal atau gerak dikurangi, halaman tetap utuh dengan keadaan awal yang rapi.
 */
(function () {
  'use strict';

  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var fine = window.matchMedia('(pointer: fine)').matches;
  var clamp = function (n) { return n < 0 ? 0 : (n > 1 ? 1 : n); };

  var hero = document.querySelector('[data-hero]');
  var how = document.querySelector('[data-how]');
  var sync = document.querySelector('[data-sync]');

  /* ---------- keadaan diam bila gerak dikurangi ---------- */
  if (reduce) {
    if (hero) hero.style.setProperty('--p', '.4');
    if (sync) { sync.style.setProperty('--s', '1'); }
    return;
  }

  /* ---------- progres tiap adegan ---------- */
  function heroProgress() {
    var pin = hero.querySelector('.hero3d__pin');
    var sticky = getComputedStyle(pin).position === 'sticky';
    var r = hero.getBoundingClientRect();
    if (sticky) return clamp(-r.top / Math.max(1, r.height - pin.offsetHeight));
    return clamp(-r.top / Math.max(1, r.height * 0.85)); // layar sempit: terbuka saat hero meninggalkan layar
  }

  var cards = how ? [].slice.call(how.querySelectorAll('.hcard')) : [];
  var steps = how ? [].slice.call(how.querySelectorAll('.how__nav li')) : [];
  var activeStep = -1;

  function howProgress() {
    var pin = how.querySelector('.how__pin');
    var sticky = getComputedStyle(pin).position === 'sticky';
    var r = how.getBoundingClientRect();
    if (!sticky) return 0;
    return clamp(-r.top / Math.max(1, r.height - pin.offsetHeight));
  }

  function setStep(i) {
    if (i === activeStep) return;
    activeStep = i;
    cards.forEach(function (c, n) {
      var k = n - i;
      c.style.setProperty('--k', Math.max(0, k));
      if (k < 0) c.setAttribute('data-past', ''); else c.removeAttribute('data-past');
    });
    steps.forEach(function (s, n) {
      if (n === i) s.setAttribute('aria-current', 'step'); else s.removeAttribute('aria-current');
    });
  }

  function syncProgress() {
    var vh = window.innerHeight, r = sync.getBoundingClientRect();
    return clamp((vh * 0.95 - r.top) / (r.height * 0.9 + vh * 0.1));
  }

  /* ---------- satu putaran per frame ---------- */
  var ticking = false;
  function frame() {
    ticking = false;
    var vh = window.innerHeight;
    if (hero) {
      var hr = hero.getBoundingClientRect();
      if (hr.bottom > -50 && hr.top < vh) hero.style.setProperty('--p', heroProgress().toFixed(4));
    }
    if (how) {
      var wr = how.getBoundingClientRect();
      if (wr.bottom > -50 && wr.top < vh) {
        var q = howProgress();
        how.style.setProperty('--q', q.toFixed(4));
        setStep(Math.min(cards.length - 1, Math.floor(q * cards.length * 0.999)));
      }
    }
    if (sync) {
      var sr = sync.getBoundingClientRect();
      if (sr.bottom > -50 && sr.top < vh) {
        var s = syncProgress();
        sync.style.setProperty('--s', s.toFixed(4));
        sync.classList.toggle('is-flowing', s > 0.45);
      }
    }
  }
  function request() { if (!ticking) { ticking = true; requestAnimationFrame(frame); } }
  window.addEventListener('scroll', request, { passive: true });
  window.addEventListener('resize', function () { activeStep = -1; request(); });
  if (how) setStep(0);
  frame();

  /* ---------- paralaks mengikuti pointer di hero (hanya mouse) ---------- */
  if (hero && fine) {
    var tx = 0, ty = 0, cx = 0, cy = 0, raf = 0;
    var loop = function () {
      cx += (tx - cx) * 0.08; cy += (ty - cy) * 0.08;
      hero.style.setProperty('--mx', cx.toFixed(3));
      hero.style.setProperty('--my', cy.toFixed(3));
      raf = (Math.abs(tx - cx) > 0.002 || Math.abs(ty - cy) > 0.002) ? requestAnimationFrame(loop) : 0;
    };
    hero.addEventListener('pointermove', function (e) {
      var r = hero.getBoundingClientRect();
      tx = ((e.clientX - r.left) / r.width - 0.5) * 2;
      ty = ((e.clientY - r.top) / r.height - 0.5) * 2;
      if (!raf) raf = requestAnimationFrame(loop);
    });
    hero.addEventListener('pointerleave', function () { tx = 0; ty = 0; if (!raf) raf = requestAnimationFrame(loop); });
  }

  /* ---------- kartu miring mengikuti pointer ---------- */
  if (fine) {
    [].forEach.call(document.querySelectorAll('[data-tilt]'), function (el) {
      el.addEventListener('pointermove', function (e) {
        var r = el.getBoundingClientRect();
        var x = (e.clientX - r.left) / r.width - 0.5, y = (e.clientY - r.top) / r.height - 0.5;
        el.style.transform = 'perspective(800px) rotateX(' + (-y * 9).toFixed(2) + 'deg) rotateY(' + (x * 11).toFixed(2) + 'deg) translateZ(6px)';
      });
      el.addEventListener('pointerleave', function () { el.style.transform = ''; });
    });
  }
})();

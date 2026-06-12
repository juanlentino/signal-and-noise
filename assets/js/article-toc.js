/**
 * In-article reading-progress bar + smooth-scroll for the TOC.
 *
 * Self-gates on the server-rendered <nav class="sn-article-toc">: if there's
 * no TOC (short note), this does nothing. Mounts a fixed progress bar driven
 * by scroll position (RAF-throttled, passive) and smooth-scrolls TOC clicks
 * (instant under prefers-reduced-motion). Pure progressive enhancement —
 * the TOC links work without it. Mirrors sticky-header.js / footnotes-popover.js.
 */
(function () {
  'use strict';

  var toc = document.querySelector('.sn-article-toc');
  if (!toc) { return; }

  // ── Reading-progress bar ────────────────────────────────────────────
  var bar = document.createElement('div');
  bar.className = 'sn-article-progress';
  bar.setAttribute('aria-hidden', 'true');
  var fill = document.createElement('span');
  fill.className = 'sn-article-progress__fill';
  bar.appendChild(fill);
  document.body.appendChild(bar);

  var ticking = false;
  function update() {
    var doc = document.documentElement;
    var scrollTop = doc.scrollTop || document.body.scrollTop || 0;
    var height = doc.scrollHeight - doc.clientHeight;
    var pct = height > 0 ? (scrollTop / height) * 100 : 0;
    if (pct < 0) { pct = 0; }
    if (pct > 100) { pct = 100; }
    fill.style.width = pct + '%';
    ticking = false;
  }
  function onScroll() {
    if (!ticking) { ticking = true; window.requestAnimationFrame(update); }
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', onScroll, { passive: true });
  update();

  // ── Smooth-scroll on TOC click (reduced-motion aware) ───────────────
  var reduce = window.matchMedia &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  toc.addEventListener('click', function (e) {
    var link = e.target && e.target.closest ? e.target.closest('a[href^="#"]') : null;
    if (!link || !toc.contains(link)) { return; }
    var id = link.getAttribute('href').slice(1);
    if (!id) { return; }
    var target = document.getElementById(id);
    if (!target) { return; }

    e.preventDefault();
    target.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });

    // Move focus to the section for keyboard users without a second jump.
    target.setAttribute('tabindex', '-1');
    target.focus({ preventScroll: true });

    // Keep the URL shareable/back-navigable without re-triggering a jump.
    if (window.history && window.history.replaceState) {
      window.history.replaceState(null, '', '#' + id);
    }
  });
})();

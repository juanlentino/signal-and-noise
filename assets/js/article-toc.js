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

  // The ARTICLE is the thing being read, so the article is what the bar
  // measures. It used to divide by the whole document —
  // (scrollTop / (scrollHeight - clientHeight)) — which counts the masthead
  // above the prose and the entire footer below it. Measured on a real note
  // (2026-08-18): the bar read 19% where the article STARTED and 43% where it
  // ENDED, so a reader who had read every word was told they were 43% through,
  // with 1048px of footer still to scroll before it reached 100%. 76% of the
  // bar's travel was spent on things that are not the article.
  var article = document.querySelector('article') || document.querySelector('.entry-content');

  // The bar is pinned under the fixed header, and the header SHRINKS on scroll
  // (.is-scrolled cuts its padding and the logo from 64px to 48px). A hardcoded
  // `top` in CSS was therefore wrong in both states and drifted further open
  // exactly when the bar matters: measured 108px against a 94px header, a 14px
  // gap of content showing through. Re-read each frame so it tracks the shrink.
  var header = document.querySelector('.sn-header');

  var ticking = false;
  function update() {
    var doc = document.documentElement;
    var scrollTop = doc.scrollTop || document.body.scrollTop || 0;
    var viewport = doc.clientHeight;

    if (header) {
      var edge = Math.round(header.getBoundingClientRect().bottom);
      if (edge >= 0) { bar.style.top = edge + 'px'; }
    }

    var pct = 0;
    if (article) {
      var rect = article.getBoundingClientRect();
      var top = rect.top + scrollTop;            // where the article begins
      var end = rect.bottom + scrollTop - viewport; // where its last line lands
      var span = end - top;
      // A note shorter than the viewport has no span to travel: it is fully
      // visible, so it is fully read. Guarding with 0% instead would leave the
      // bar empty on exactly the notes a reader finishes fastest.
      pct = span > 0 ? ((scrollTop - top) / span) * 100 : (scrollTop >= top ? 100 : 0);
    }
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

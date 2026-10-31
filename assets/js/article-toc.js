/**
 * Smooth-scroll and focus for the in-article TOC.
 *
 * Self-gates on the server-rendered <nav class="sn-article-toc">: if there's
 * no TOC (short note), this does nothing. Smooth-scrolls TOC clicks (instant
 * under prefers-reduced-motion) and moves focus to the section. Pure
 * progressive enhancement: the TOC links work without it.
 *
 * The reading-progress bar this file used to draw and re-measure on every
 * scroll frame is gone (2026-10): the progress indicator is now the FM tuning
 * dial, server-rendered in the nav (inc/article-toc.php) and moved by a CSS
 * scroll-driven animation (assets/css/components-12-article-toc.css).
 */
(function () {
  'use strict';

  var toc = document.querySelector('.sn-article-toc');
  if (!toc) { return; }

  // ── Smooth-scroll on TOC click (reduced-motion aware) ───────────────
  var reduce = window.matchMedia &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  toc.addEventListener('click', function (e) {
    // A modified click means a new tab, window or download: the browser's.
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
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

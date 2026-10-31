/**
 * Smooth-scroll and focus for the in-article TOC.
 *
 * Self-gates on the server-rendered <nav class="sn-article-toc">: if there's
 * no TOC (short note), this does nothing. Smooth-scrolls TOC clicks (instant
 * under prefers-reduced-motion) and moves focus to the section, and draws a
 * copy-link button in each H2 the TOC names (through sn-copy.js). Pure
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

  // ── Section links (2026-10-31) ──────────────────────────────────────
  // A copy-link button inside each H2 the contents list names. Copying needs
  // script, so the button is drawn here, never by the server: feeds, the
  // Markdown and JSON twins and no-JS readers never meet a dead one. The
  // heading keeps its own text as its accessible name, so a screen reader's
  // heading list does not also read "Copy link to section".
  var copy = window.snCopy; // sn-copy.js, this script's dependency.
  if (!copy || !toc.querySelectorAll) { return; }
  // The canonical address drops tracking parameters; one on another host (a
  // republished note) is not this page, and its section ids are this page's.
  var canon = document.querySelector('link[rel="canonical"]');
  var here = window.location.origin + window.location.pathname;
  var base = (canon && canon.href && canon.href.indexOf(window.location.origin + '/') === 0 ? canon.href : here).split('#')[0];
  var tocLinks = toc.querySelectorAll('a[href^="#"]');
  // In place before the first message: a region added along with its text may say nothing.
  var live = tocLinks.length ? copy.liveRegion(document.body, 'sn-section-link__status') : null;
  for (var i = 0; i < tocLinks.length; i++) { addSectionLink(tocLinks[i]); }

  function addSectionLink(tocLink) {
    var sid = tocLink.getAttribute('href').slice(1);
    var heading = sid ? document.getElementById(sid) : null;
    if (!heading || heading.tagName !== 'H2') { return; }
    var label = String(tocLink.textContent || '').trim();
    var btn = document.createElement('button');
    var timer = null;
    btn.type = 'button';
    btn.className = 'sn-section-link';
    btn.setAttribute('aria-label', 'Copy link to section: ' + label);
    btn.textContent = '#';
    heading.setAttribute('aria-label', label);
    heading.appendChild(btn);
    btn.addEventListener('click', function () {
      copy.text(base + '#' + sid).then(
        function () { show('copied', 'Link to section copied'); },
        function () { show('failed', 'Copy failed'); }
      );
    });
    // The label stays "#": the state shows as a CSS label above it, out of
    // flow, so a copy never reflows the heading (review of #621).
    function show(state, msg) {
      live.textContent = ''; // cleared first, so the same message is announced again.
      live.textContent = msg;
      btn.setAttribute('data-sn-state', state);
      if (timer) { clearTimeout(timer); }
      timer = setTimeout(function () { btn.removeAttribute('data-sn-state'); }, 1600);
    }
  }
})();

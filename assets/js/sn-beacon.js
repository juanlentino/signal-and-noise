/**
 * Signal & Noise — first-party edge analytics beacon.
 * Cookieless. No-ops entirely under DNT/GPC. Posts same-origin to the
 * Cloudflare Worker route (window.SN_BEACON.endpoint). See P1 plan + spec
 * docs/superpowers/specs/2026-06-11-first-party-edge-analytics-design.md.
 * v10.4.0: adds window.SN_BEACON.event(name, props) for named custom events
 * (a 'ce' beacon → worker ce/cp rows). The API is a no-op under DNT/GPC.
 */
(function () {
  var cfg = window.SN_BEACON;
  if (!cfg || !cfg.endpoint || !cfg.k) return;

  // Custom-event API: a no-op stub set BEFORE the privacy gate so callers can
  // always invoke window.SN_BEACON.event(name, props) without a guard. Under
  // DNT/GPC the beacon bails entirely and this stays a no-op; on the tracked
  // path it is reassigned to the real sender at the end of the IIFE.
  cfg.event = function () {};

  // Privacy gate — bail completely (no listeners, no beacon) when opted out.
  var dnt = navigator.doNotTrack === '1' || window.doNotTrack === '1' || navigator.msDoNotTrack === '1';
  if (dnt || navigator.globalPrivacyControl === true) return;

  function send(payload) {
    var json = JSON.stringify(Object.assign({ k: cfg.k }, payload));
    if (navigator.sendBeacon) {
      var ok = navigator.sendBeacon(cfg.endpoint, new Blob([json], { type: 'application/json' }));
      if (ok) return;
    }
    // Fallback: keepalive survives document teardown.
    try {
      fetch(cfg.endpoint, { method: 'POST', keepalive: true, headers: { 'Content-Type': 'application/json' }, body: json }).catch(function () {});
    } catch (e) { /* fire-and-forget */ }
  }

  function pageview() {
    send({ e: 'pv', u: location.pathname, r: document.referrer || '' });
  }

  // 1) Pageview on load + on bfcache restore (Back button = a new view).
  pageview();
  window.addEventListener('pageshow', function (ev) { if (ev.persisted) pageview(); });

  // 2) Scroll milestones 25/50/75/100, each once, passive + rAF-throttled.
  var sent = {}, ticking = false;
  function checkScroll() {
    ticking = false;
    var doc = document.documentElement;
    var scrollable = doc.scrollHeight - window.innerHeight;
    var pct = scrollable <= 0 ? 100 : Math.min(100, Math.round((window.scrollY / scrollable) * 100));
    [25, 50, 75, 100].forEach(function (m) {
      if (pct >= m && !sent[m]) { sent[m] = 1; send({ e: 'sc', u: location.pathname, d: m }); }
    });
    if (sent[100]) window.removeEventListener('scroll', onScroll);
  }
  function onScroll() { if (!ticking) { ticking = true; requestAnimationFrame(checkScroll); } }
  window.addEventListener('scroll', onScroll, { passive: true });
  checkScroll();

  // 3) Visible time-on-page; accumulate only while visible; flush once on exit.
  var visibleMs = 0, lastVisible = document.visibilityState === 'visible' ? performance.now() : null, flushed = false;
  function accumulate() { if (lastVisible !== null) { visibleMs += performance.now() - lastVisible; lastVisible = null; } }
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') { accumulate(); flush(); }
    else { lastVisible = performance.now(); }
  });
  function flush() {
    if (flushed) return;
    flushed = true;
    accumulate();
    send({ e: 'tm', u: location.pathname, ms: Math.round(visibleMs) });
  }
  window.addEventListener('pagehide', flush);
  window.addEventListener('pageshow', function (ev) { if (ev.persisted) { flushed = false; visibleMs = 0; lastVisible = performance.now(); sent = {}; window.addEventListener('scroll', onScroll, { passive: true }); } });

  // 4) Custom events — window.SN_BEACON.event(name, props). On the tracked path
  // this replaces the no-op stub. Clamps name (64) + up to 4 string→string props
  // (key 60, value 180), then posts a 'ce' beacon. The worker writes a 'ce' base
  // row + one 'cp' row per property.
  cfg.event = function (name, props) {
    var n = String(name || '').slice(0, 64);
    if (!n) return;
    var pr = {};
    if (props && typeof props === 'object') {
      var c = 0;
      for (var k in props) {
        if (c++ >= 4) break;
        pr[String(k).slice(0, 60)] = String(props[k]).slice(0, 180);
      }
    }
    send({ e: 'ce', u: location.pathname, r: document.referrer || '', n: n, pr: pr });
  };
})();

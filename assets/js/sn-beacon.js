/**
 * Signal & Noise — first-party edge analytics beacon.
 * Cookieless. No-ops entirely under DNT/GPC. Posts same-origin to the
 * Cloudflare Worker route (window.SN_BEACON.endpoint). See P1 plan + spec
 * docs/superpowers/specs/2026-06-11-first-party-edge-analytics-design.md.
 * v10.4.0: adds window.SN_BEACON.event(name, props) for named custom events
 * (a 'ce' beacon → worker ce/cp rows). The API is a no-op under DNT/GPC.
 * v10.26.0: auto-fires goal-action 'ce' events for conversion-relevant clicks,
 * cookielessly, via one delegated listener (see section 6). Short, stable event
 * names the plugin's funnels reference:
 *   - outbound   {host}           — any cross-host http(s) link (hostname only)
 *   - subscribe  {target:'rss'}   — any RSS/feed link (feed type / /feed/ / ?feed=)
 *   - subscribe  {target:'<t>'}   — any element with data-sn-subscribe="<t>" (e.g. email)
 *   - <name>                      — any element with data-sn-goal="<name>" (add goals, no JS change)
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

  // 5) Core Web Vitals (field) — real-user LCP/INP/CLS via the web-vitals lib
  // (window.webVitals, enqueued before this script). Each metric is sent as its own
  // tiny event when the library finalizes it (on interaction / page-hide): vl=LCP,
  // vi=INP, vc=CLS. CLS is unitless → sent x1000 as an integer so it stores cleanly.
  // No-op when the lib is absent (e.g. an unsupported browser).
  if (window.webVitals) {
    var reportVital = function (ev, scale) {
      return function (metric) {
        send({ e: ev, u: location.pathname, v: Math.round(metric.value * scale) });
      };
    };
    window.webVitals.onLCP(reportVital('vl', 1));
    window.webVitals.onINP(reportVital('vi', 1));
    window.webVitals.onCLS(reportVital('vc', 1000));
  }

  // 6) Goal-action clicks (v10.26.0) — cookieless conversion signals via ONE
  // delegated listener (no per-element binding, so goals added to the DOM later
  // are tracked with zero JS changes). Each click fires AT MOST ONE 'ce' event
  // through cfg.event() above, so nothing double-counts. Priority is author
  // intent first, then automatic classification:
  //   a) [data-sn-goal="<name>"]     → event('<name>')                (any element)
  //   b) [data-sn-subscribe="<tgt>"] → event('subscribe',{target:tgt})(any element)
  //   c) an RSS/feed link            → event('subscribe',{target:'rss'})
  //   d) a cross-host http(s) link   → event('outbound',{host})       HOST ONLY
  // Outbound sends the destination HOSTNAME ONLY — never the path or query — so no
  // page/query-string PII ever leaves the browser. This whole section runs after
  // the DNT/GPC gate, so an opted-out visitor never binds the listener; a mailto:
  // or tel: link matches nothing (protocol isn't http/https), so DOM-built contact
  // links are never miscounted as conversions.
  function feedLink(a, url) {
    var type = (a.getAttribute('type') || '').toLowerCase();
    if (/(rss|atom)\+xml|feed\+json/.test(type)) return true;
    if ('http:' !== url.protocol && 'https:' !== url.protocol) return false;
    return /(^|\/)feed(\/|$)/i.test(url.pathname) || /[?&]feed=/i.test(url.search);
  }
  // Downloadable file extensions (v10.40.0). A link to one of these — or any link
  // carrying a `download` attribute — fires 'download' with the EXTENSION ONLY, never
  // the filename or path, staying consistent with the host-only outbound stance.
  var DOWNLOAD_EXT = /\.(pdf|zip|docx?|xlsx?|pptx?|csv|rtf|odt|ods|odp|mp3|wav|mp4|mov|avi|mkv|dmg|exe|pkg|deb|rpm|apk|gz|tgz|tar|rar|7z|epub|mobi)$/i;
  document.addEventListener('click', function (ev) {
    var t = ev.target;
    if (!t || !t.closest) return; // text/non-Element targets can't be conversions

    // a) Explicit named goal — highest priority, works on ANY element.
    var goalEl = t.closest('[data-sn-goal]');
    if (goalEl) {
      var goal = (goalEl.getAttribute('data-sn-goal') || '').trim();
      if (goal) cfg.event(goal);
      return; // matched an explicit goal → never fall through to link classification
    }
    // b) Explicit subscribe (e.g. an email-newsletter link), works on ANY element.
    var subEl = t.closest('[data-sn-subscribe]');
    if (subEl) {
      var target = (subEl.getAttribute('data-sn-subscribe') || '').trim();
      cfg.event('subscribe', target ? { target: target } : null);
      return;
    }
    // c/d) Automatic link classification.
    var a = t.closest('a[href]');
    if (!a || !a.href) return;
    var url;
    try { url = new URL(a.href); } catch (e) { return; } // a.href is already absolute
    if (feedLink(a, url)) { cfg.event('subscribe', { target: 'rss' }); return; }
    if ('http:' === url.protocol || 'https:' === url.protocol) {
      // c1) File download (same- or cross-host) — extension ONLY, more specific than
      // 'outbound', so it wins for a cross-host file link. No filename/path/query.
      var dext = (url.pathname.match(/\.([a-z0-9]{1,8})$/i) || [])[1];
      if (a.hasAttribute('download') || (dext && DOWNLOAD_EXT.test('.' + dext.toLowerCase()))) {
        cfg.event('download', dext ? { ext: dext.toLowerCase() } : null);
        return;
      }
      // d) Cross-host outbound link — hostname ONLY, no path, no query.
      if (url.hostname && url.hostname !== location.hostname) {
        cfg.event('outbound', { host: url.hostname });
      }
    }
  });
})();

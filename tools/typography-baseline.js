/**
 * Typography baseline capture — run in the browser console (or the Claude
 * Code browser pane) against the LIVE site.
 *
 * WHY THIS IS A SCRIPT AND NOT A STORED BASELINE FILE. The comparison that
 * matters is "before install" vs "after install", and those two moments can be
 * days apart. A baseline captured when the work STARTS goes stale the moment a
 * note is published or a page is edited, and every such edit then reads as a
 * typography regression. Capture immediately before installing, and again
 * immediately after. Same instrument, minutes apart, so content churn cannot
 * masquerade as a rendered change.
 *
 * WHY IFRAMES. Computed styles require layout. A DOMParser document is never
 * laid out, so getComputedStyle() there returns initial values rather than the
 * cascade's result — which is the one thing this is measuring. Same-origin
 * iframes give real layout for every page in a single pass.
 *
 * WHY THE FILTER IS BROAD. It selects any element carrying inline typography,
 * a *-font-size class, or an is-style-* class. After migration the SAME
 * elements are selected by their new classes rather than their old inline
 * styles, so the two runs stay comparable across the mechanism change.
 *
 * WHAT IT CANNOT SEE. templates/page-notes.html is a fallback that renders only
 * if inc/page-notes-render.php is missing from disk, so its 17 sites never
 * appear here. That is expected; verify those at source level.
 *
 * Usage: paste the IIFE below, save output as before.json / after.json, diff.
 *
 * @since theme v12.5.0
 */
(async () => {
  const urls = ['/','/notes/','/about/','/services/','/resume/','/contact/'];
  const PROPS = ['font-size','letter-spacing','text-transform','line-height','font-style','font-weight'];
  const TYPO = /font-size|letter-spacing|text-transform|line-height|font-style/;
  const load = (u) => new Promise((res) => {
    const f = document.createElement('iframe');
    f.style.cssText = 'width:1280px;height:900px;position:fixed;left:-9999px;top:0;border:0';
    f.src = u;
    f.onload = () => res(f);
    document.body.appendChild(f);
  });
  const lines = [];
  for (const u of urls) {
    const f = await load(u);
    const d = f.contentDocument;
    const els = [...d.querySelectorAll('body *')].filter(el => {
      const st = el.getAttribute('style') || '';
      return TYPO.test(st) || [...el.classList].some(c => /-font-size$/.test(c) || /^is-style-/.test(c));
    });
    els.forEach(el => {
      const cs = f.contentWindow.getComputedStyle(el);
      const key = el.tagName.toLowerCase() +
        (el.className ? '.' + String(el.className).trim().split(/\s+/).slice(0,3).join('.') : '');
      const text = (el.textContent || '').trim().slice(0, 32).replace(/\s+/g, ' ');
      lines.push([u, key, text, ...PROPS.map(p => cs.getPropertyValue(p))].join(' | '));
    });
    f.remove();
  }
  // Sorted so the diff is order-independent: WordPress may reorder equivalent
  // nodes between renders, and that is not a regression.
  return lines.sort().join('\n');
})()

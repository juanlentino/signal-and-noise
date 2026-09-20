<?php
/**
 * Dynamic render for signal-noise/meta-nav: the footer meta-nav, one <nav>
 * of five icon links (Now, Accessibility, Colophon, Stats, Privacy) with
 * middot separators. It was one wp:html block in parts/footer.html (the
 * paragraph block strips SVG from rich text); as a PHP-only block it is
 * named in the site editor and moves as one unit (#388).
 *
 * The output is the wp:html block carried, byte for byte, including the
 * newline and indentation the delimiters wrapped it in: the nowdoc opens
 * on an empty line and the tail is concatenated so no source line ends in
 * whitespace. tests/footer-meta-nav.php pins it against
 * tests/fixtures/footer-meta-nav.html, the capture from 13.4.0 through
 * WP_Block_Parser. Never autop, the same as the toggle.
 *
 * The glyphs: mono stroke marks in the brutalist vocabulary. A clock is
 * Now, the standard accessibility figure, a pilcrow (the printer mark) is
 * Colophon, three bars are Stats, a shield is Privacy. None has a universal
 * reading, so every link carries an aria-label and a title, and each svg
 * is decorative (aria-hidden, unfocusable). The nav is rust and the marks
 * draw with currentColor; hover brings blood (.sn-footer__meta-nav in
 * assets/css/layout.css). The hrefs are path-relative and resolve against
 * the current origin.
 *
 * Why the marks are still literals and not wp_get_icon(): the 7.1 icon
 * registry sanitizes every icon through a fixed allowlist (svg, path,
 * polygon; no stroke attributes, no circle), which strips these stroked
 * marks to unfilled paths. Registering them needs a redraw as filled
 * paths and a pixel acceptance, and a floor of 7.1 or a function_exists
 * guard; both are the owner call the issue names. When that lands, each
 * <svg> below becomes one wp_get_icon() call and nothing else moves.
 *
 * @package SignalNoise
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
echo <<<'HTML'

		<nav class="sn-footer__meta-nav" aria-label="Site meta">
			<a href="/now" aria-label="Now: what I&#8217;m focused on" title="Now"><svg viewBox="0 0 16 16" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg"><circle cx="8" cy="8" r="6.25"/><path d="M8 4.75V8l2.25 1.5"/></svg></a>
			<span class="sn-footer__meta-sep" aria-hidden="true">&middot;</span>
			<a href="/accessibility" aria-label="Accessibility statement" title="Accessibility"><svg viewBox="0 0 16 16" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg"><circle cx="8" cy="2.9" r="1.4" fill="currentColor" stroke="none"/><path d="M2.9 5.6c3.4 1 6.8 1 10.2 0"/><path d="M8 6.4v3.4"/><path d="M8 9.8 5.9 13.6"/><path d="M8 9.8l2.1 3.8"/></svg></a>
			<span class="sn-footer__meta-sep" aria-hidden="true">&middot;</span>
			<a href="/colophon/" aria-label="Colophon: how this site is made" title="Colophon"><svg viewBox="0 0 16 16" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg"><path d="M12.7 2.9H6.4a3 3 0 0 0 0 6h2.3"/><path d="M8.7 2.9v10.2"/><path d="M11.3 2.9v10.2"/></svg></a>
			<span class="sn-footer__meta-sep" aria-hidden="true">&middot;</span>
<a href="/stats" aria-label="Stats: what gets read here" title="Stats"><svg viewBox="0 0 16 16" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg"><path d="M4 13.2V9.4"/><path d="M8 13.2V6.2"/><path d="M12 13.2V3.4"/></svg></a>
<span class="sn-footer__meta-sep" aria-hidden="true">&middot;</span>
			<a href="/privacy-policy" aria-label="Privacy policy" title="Privacy"><svg viewBox="0 0 16 16" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg"><path d="M8 2 3 3.9v4c0 3 2.1 5.2 5 6.2 2.9-1 5-3.2 5-6.2v-4L8 2Z"/><path d="M6 7.9 7.4 9.3 10.2 6.3"/></svg></a>
		</nav>
HTML . "\n\t\t";

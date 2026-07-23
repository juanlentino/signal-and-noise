<?php
/**
 * Signal & Noise — Combined front-end stylesheet delivery.
 *
 * The modular CSS sources (assets/css/*.css) stay separate in the repo for
 * editing clarity; production serves ONE combined + lightly minified
 * stylesheet from uploads/sn-css/. inc/assets-frontend.php long documented
 * that "Breeze will concatenate them in production anyway" — that
 * concatenation never materialized at the cache layer (surfaced by the
 * Performance Lab blocking-assets audit, 2026-07-02), so the theme owns it
 * now instead of depending on cache-plugin config.
 *
 * Performance Lab context (source-verified, audit-enqueued-assets/helper.php):
 * the styles warning fires at count > 10 OR bytes > 100000. The live site sat
 * at 6 files / ~100.4 KB — 0.3% over the SIZE threshold. Minification is what
 * clears the size half (the sources are hand-written and comment-rich);
 * combining clears request count.
 *
 * Fail-open contract: ANY problem — missing source, unwritable uploads, a
 * relative url() that would break when served from uploads/ — returns null
 * and the enqueue callers fall back to the original per-file stylesheets.
 * This optimization can never cost the site its styling.
 *
 * Note: the combined file always carries command-palette.css even when the
 * palette feature is disabled (~5 KB of dead rules in that case). Deliberate:
 * keying the hash on a runtime setting would fragment the cache for a
 * negligible saving.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bump when sn_css_minify()'s transforms change, so already-built combined
 * files regenerate under a new hash.
 */
define( 'SN_CSS_COMBINE_SCHEME', 'v1' );

/**
 * The ordered stylesheet sources, mirroring the cascade the per-file
 * enqueues enforce via dependencies: base → layout → components →
 * responsive (media overrides last) → command-palette (standalone chrome)
 * → article (v10.49.0: the single-note back half moved out of the inlined
 * critical.css; it MUST stay LAST — before the move those rules were the
 * last stylesheet layer in the document, so any earlier position could let
 * previously-later rules start losing source-order ties).
 * print.css is NOT here — it loads with media="print" and never blocks.
 * Keep in sync with the fallback enqueues in inc/assets-frontend.php and
 * the editor list in inc/setup.php.
 *
 * @return string[] Theme-relative paths.
 */
function sn_css_combine_sources() {
	return array(
		'assets/css/base.css',
		'assets/css/layout.css',
		'assets/css/components.css',
		'assets/css/responsive.css',
		'assets/css/command-palette.css',
		'assets/css/article.css',
	);
}

/**
 * Light, safe CSS minification: strips comments, collapses whitespace runs,
 * trims spaces around braces/semicolons, drops semicolons before a closing
 * brace. Deliberately does NOT touch colons, commas, or parens content —
 * "a :hover" (descendant) vs "a:hover" and calc()'s required operator
 * spacing make those transforms unsafe without a real tokenizer.
 *
 * @param string $css Raw CSS.
 * @return string Minified CSS.
 */
function sn_css_minify( $css ) {
	$css = preg_replace( '~/\*.*?\*/~s', '', (string) $css );
	$css = preg_replace( '~\s+~', ' ', $css );
	$css = preg_replace( '~ *([{};]) *~', '$1', $css );
	$css = str_replace( ';}', '}', $css );
	return trim( $css );
}

/**
 * Content signature for the current source set: scheme + each source's
 * path and mtime. Theme updates rewrite every file (fresh mtimes), so each
 * release combines under a new hash and old URLs simply expire from edge
 * caches — no purge coordination needed.
 *
 * @return string|null 12-char hash, or null when any source is unreadable.
 */
function sn_css_combine_signature() {
	$stamp = SN_CSS_COMBINE_SCHEME;
	foreach ( sn_css_combine_sources() as $rel ) {
		$file = get_theme_file_path( $rel );
		if ( ! file_exists( $file ) ) {
			return null;
		}
		$mtime = filemtime( $file );
		if ( ! $mtime ) {
			return null;
		}
		$stamp .= '|' . $rel . ':' . $mtime;
	}
	return substr( md5( $stamp ), 0, 12 );
}

/**
 * Ensure the combined stylesheet exists and return its envelope, or null
 * to signal the callers to fall back to per-file enqueues. Memoized per
 * request in $GLOBALS['sn_css_combined_memo'] (a global, not a static, so
 * the standalone tests can reset it between scenarios).
 *
 * @return array{file: string, url: string, ver: string}|null
 */
function sn_css_ensure_combined() {
	// array_key_exists, not isset: a memoized NULL (fallback mode) must
	// also short-circuit — isset() reports false for stored nulls.
	if ( array_key_exists( 'sn_css_combined_memo', $GLOBALS ) ) {
		return $GLOBALS['sn_css_combined_memo'];
	}
	$GLOBALS['sn_css_combined_memo'] = null;

	$hash = sn_css_combine_signature();
	if ( null === $hash ) {
		return null;
	}

	$uploads = wp_upload_dir();
	if ( ! empty( $uploads['error'] ) ) {
		return null;
	}
	$dir    = $uploads['basedir'] . '/sn-css';
	$target = $dir . '/sn-styles-' . $hash . '.css';

	if ( ! file_exists( $target ) ) {
		if ( ! wp_mkdir_p( $dir ) ) {
			return null;
		}
		$css = '';
		foreach ( sn_css_combine_sources() as $rel ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- repo-shipped theme CSS, same trust model as the critical.css inliner in inc/assets-frontend.php.
			$raw = file_get_contents( get_theme_file_path( $rel ) );
			if ( false === $raw ) {
				return null;
			}
			if ( preg_match( '~url\(\s*(?![\'"]?(?:data:|https?:|//|/|#|%23))~i', $raw ) ) {
				// A relative url() would resolve against uploads/sn-css/,
				// not the theme's css dir. No rewriter exists (no current
				// source needs one) — fail open to the per-file enqueues.
				// Allowed prefixes = location-independent references only:
				// data:/https?:/protocol-relative/absolute, plus `#`
				// (same-document SVG fragment, not a file fetch) and `%23`
				// (the ENCODED '#' — url(%23noise) INSIDE an encoded SVG
				// data: payload is what base.css's grain texture contains;
				// it bit v10.21.7 because the guard scans every url(
				// occurrence, including ones inside an already-safe data:
				// URI). %23 specifically, NOT bare `%`: the WHATWG URL
				// Standard normalizes %2e/%2e%2e to the dot-segments ./..
				// so a bare-% allowance waves through percent-encoded
				// RELATIVE paths (2026-07-02 audit). Any other %-start
				// fails open — safe by contract.
				// v10.21.7 history: the optional quote must live INSIDE
				// the lookahead — outside it backtracks to empty and the
				// guard false-positives on every quoted url().
				return null;
			}
			$css .= '/* ' . $rel . " */\n" . sn_css_minify( $raw ) . "\n";
		}
		// PID-suffixed so two concurrent cold-cache builders never share a
		// temp path (identical bytes either way, but interleaved writes to
		// one shared .tmp are wasted I/O; the glob prune below only matches
		// *.css so orphaned temps are never collected as build artifacts).
		$tmp = $target . '.' . getmypid() . '.tmp';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- direct write + atomic rename into uploads; WP_Filesystem adds credential-prompt failure modes for zero benefit on this same-host path.
		if ( false === file_put_contents( $tmp, $css ) ) {
			return null;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- atomic same-directory swap.
		if ( ! rename( $tmp, $target ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- cleanup of our own temp file.
			unlink( $tmp );
			return null;
		}
		foreach ( (array) glob( $dir . '/sn-styles-*.css' ) as $old ) {
			if ( $old !== $target ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- pruning our own previous-hash artifacts.
				unlink( $old );
			}
		}
		foreach ( (array) glob( $dir . '/sn-styles-*.tmp' ) as $orphan ) {
			// Crash-orphaned temps only — age-gated so a concurrent
			// builder's LIVE temp is never deleted mid-write (that would
			// fail its rename and force a needless per-file fallback).
			if ( filemtime( $orphan ) < time() - 3600 ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- cleanup of our own crash-orphaned temp files.
				unlink( $orphan );
			}
		}
	}

	$GLOBALS['sn_css_combined_memo'] = array(
		'file' => $target,
		'url'  => $uploads['baseurl'] . '/sn-css/sn-styles-' . $hash . '.css',
		'ver'  => $hash,
	);
	return $GLOBALS['sn_css_combined_memo'];
}

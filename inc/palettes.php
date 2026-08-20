<?php
/**
 * Signal & Noise — every palette the site can present.
 *
 * THE SITE HAS NEVER SERVED ONE PALETTE, and every surface that assumed it did
 * has eventually reported a half-truth:
 *
 *   - tests/contrast-baseline.php read theme.json and reported 20 passed /
 *     delta 0.00 while the ACTIVE High Contrast palette failed AA.
 *   - theme v11.7.1 moved a token in theme.json that the activated variation's
 *     database copy shadowed, and shipped a release that was half inert.
 *   - get-design-tokens returned a flat slug => hex map right up to v12.0.0,
 *     and those values are embedded into AI prompts.
 *
 * So the palettes are enumerated ONCE, here, and every consumer — the ability,
 * the AI prompt formatters, the contrast suite — reads them through this
 * module. Nothing restates a palette.
 *
 * TWO ORTHOGONAL AXES, deliberately not flattened into one:
 *
 *   VARIATION — which palette the owner activated (root / high-contrast / …).
 *               A WordPress style variation. Always a LIGHT palette today.
 *   SCHEME    — what the reader's OS or toggle asks for (light / dark).
 *
 * They are not interchangeable. `dark` is not a variation: the token layer in
 * critical.css overrides `--wp--preset--color--*` at :root, so it replaces
 * whatever variation is active rather than sitting beside it. Keying everything
 * into one flat namespace would say High Contrast and dark are alternatives to
 * each other, which is false — a High Contrast reader on a dark OS gets dark.
 *
 * @package SignalNoise
 * @since   theme v12.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The dark palette, read from the stylesheet that actually ships it.
 *
 * Parsed rather than restated. A PHP copy of these values would not drift
 * loudly — it would drift silently and keep passing, which is exactly how
 * v11.7.1 shipped inert.
 *
 * @since theme v12.0.0
 * @return array<string,string> slug => lowercase hex. Empty if the block is absent.
 */
function sn_theme_dark_palette() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$cache = array();

	$file = get_theme_file_path( 'assets/css/critical.css' );
	if ( ! file_exists( $file ) ) {
		return $cache;
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- repo-shipped, theme-owned CSS; same trust model as the critical.css inliner in inc/assets-frontend.php.
	$css = (string) file_get_contents( $file );

	// Comments first: the block above the palette names the selector in prose,
	// so a raw search finds the explanation rather than the rule.
	$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );

	$at = strpos( $css, ':root[data-theme="dark"]' );
	if ( false === $at ) {
		return $cache;
	}
	$open  = strpos( $css, '{', $at );
	$close = false === $open ? false : strpos( $css, '}', $open );
	if ( false === $open || false === $close ) {
		return $cache;
	}

	if ( preg_match_all(
		'/--wp--preset--color--([a-z0-9-]+)\s*:\s*(#[0-9a-fA-F]{3,8})/',
		substr( $css, $open, $close - $open ),
		$m,
		PREG_SET_ORDER
	) ) {
		foreach ( $m as $hit ) {
			$cache[ $hit[1] ] = strtolower( $hit[2] );
		}
	}

	return $cache;
}

/**
 * Read a palette out of a theme.json-shaped file.
 *
 * @since theme v12.0.0
 * @param string $path Absolute path.
 * @return array<string,string> slug => lowercase hex.
 */
function sn_theme_palette_from_json( $path ) {
	if ( ! file_exists( $path ) ) {
		return array();
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- repo-shipped, theme-owned JSON.
	$data = json_decode( (string) file_get_contents( $path ), true );
	$out  = array();
	foreach ( ( $data['settings']['color']['palette'] ?? array() ) as $entry ) {
		if ( isset( $entry['slug'], $entry['color'] ) ) {
			$out[ (string) $entry['slug'] ] = strtolower( (string) $entry['color'] );
		}
	}
	return $out;
}

/**
 * Every palette the site can present, keyed by palette IDENTITY.
 *
 * Identity, not colour scheme, is the key space — so a fourth palette (another
 * variation, or a dark variation) is an ADDITIVE key rather than a second
 * breaking change to the same field. Each entry carries its own `scheme`, which
 * is the axis a key name alone cannot express.
 *
 * `root` and each `styles/*.json` variation come from the files that define
 * them. Note this is the DEFAULT each ships, not necessarily what the live site
 * serves: activating a variation COPIES it into the `wp_global_styles` post,
 * and that copy wins forever after. `sn_theme_served_palette_id()` answers the
 * separate question of which one is live.
 *
 * @since theme v12.0.0
 * @return array<string,array{scheme:string,source:string,colors:array<string,string>}>
 */
function sn_theme_all_palettes() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$root  = sn_theme_palette_from_json( get_theme_file_path( 'theme.json' ) );
	$cache = array();

	if ( $root ) {
		$cache['root'] = array(
			'scheme' => 'light',
			'source' => 'theme.json',
			'colors' => $root,
		);
	}

	foreach ( (array) glob( get_theme_file_path( 'styles' ) . '/*.json' ) as $file ) {
		$overrides = sn_theme_palette_from_json( $file );
		if ( ! $overrides ) {
			continue;
		}
		// A variation overrides only the slugs it names; the rest fall through
		// to root. Merging here means every entry is a COMPLETE palette, so no
		// consumer has to know the fallback rule to read one correctly.
		$cache[ basename( $file, '.json' ) ] = array(
			'scheme' => 'light',
			'source' => 'styles/' . basename( $file ),
			'colors' => array_merge( $root, $overrides ),
		);
	}

	$dark = sn_theme_dark_palette();
	if ( $dark ) {
		$cache['dark'] = array(
			'scheme' => 'dark',
			'source' => 'assets/css/critical.css',
			'colors' => array_merge( $root, $dark ),
		);
	}

	return $cache;
}

/**
 * Which palette the site is actually serving right now.
 *
 * Answered by MATCHING the resolved palette against the shipped ones, never by
 * assuming theme.json. Activating a variation copies it into `wp_global_styles`
 * and that copy wins, so theme.json is the default style, not necessarily the
 * served one — the distinction that made a v11.7.1 token fix land inert while
 * the release reported success.
 *
 * Returns 'custom' when the resolved palette matches nothing shipped. That is
 * not an error: it means the owner edited colours in the Site Editor, and a
 * consumer should know the repo cannot describe what is live.
 *
 * @since theme v12.0.0
 * @return string Palette id, 'custom', or '' when WordPress cannot be asked.
 */
function sn_theme_served_palette_id() {
	if ( ! function_exists( 'wp_get_global_settings' ) ) {
		return '';
	}
	$resolved = array();
	$palette  = wp_get_global_settings( array( 'color', 'palette' ) );

	// wp_get_global_settings() hands back either a flat entry list or origin
	// buckets (default/theme/custom) depending on the merge. Both shapes appear
	// in the wild; the existing reader in abilities-diagnostics.php flattens
	// them for the same reason.
	$entries = array();
	if ( isset( $palette['slug'] ) ) {
		$entries = array( $palette );
	} else {
		foreach ( (array) $palette as $maybe ) {
			if ( isset( $maybe['slug'], $maybe['color'] ) ) {
				$entries[] = $maybe;
			} elseif ( is_array( $maybe ) ) {
				foreach ( $maybe as $inner ) {
					if ( isset( $inner['slug'], $inner['color'] ) ) {
						$entries[] = $inner;
					}
				}
			}
		}
	}
	foreach ( $entries as $entry ) {
		$resolved[ (string) $entry['slug'] ] = strtolower( (string) $entry['color'] );
	}
	if ( ! $resolved ) {
		return '';
	}

	// SUBSET MATCH ON THE THEME'S OWN SLUGS — not set equality.
	//
	// v12.0.3 required the two maps to be identical in both directions, and on
	// the live site that could never be true: wp_get_global_settings() returns
	// the theme palette PLUS WordPress's twelve core defaults (black, pale-pink,
	// vivid-red, …), which no shipped palette declares. So the diff was never
	// empty and this returned 'custom' on a site running High Contrast — the one
	// field that answers "what is actually live" was reporting that the owner
	// had hand-edited colours when they had not.
	//
	// It passed its test because the fixture contained only theme slugs. The
	// fixture was cleaner than reality, which is the tell: shapes have to come
	// from the emitter, not from what is convenient to write.
	//
	// A palette matches when every slug IT declares is present in the resolved
	// set with the same value. Extra slugs in `resolved` are WordPress's, not
	// ours, and say nothing about which of our palettes is active.
	foreach ( sn_theme_all_palettes() as $id => $meta ) {
		if ( 'dark' === $id ) {
			continue; // Not selectable — it is a reader-side override.
		}
		$matches = true;
		foreach ( $meta['colors'] as $slug => $hex ) {
			if ( ! isset( $resolved[ $slug ] ) || $resolved[ $slug ] !== $hex ) {
				$matches = false;
				break;
			}
		}
		if ( $matches ) {
			return $id;
		}
	}

	return 'custom';
}
